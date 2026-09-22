<?php
declare(strict_types=1);

/**
 * Hybrid chatbot engine.
 *
 * 1. Local intent matching answers factual questions instantly and for free
 *    from config.php + the MySQL project cache (skills, projects, education,
 *    contact, availability, …).
 * 2. When a question doesn't match any intent AND an OpenAI key is
 *    configured (config.php → ai.api_key), it is forwarded to the AI with a
 *    system prompt built from the same portfolio data.
 * 3. Otherwise a friendly fallback with quick suggestion chips is returned.
 *
 * Every turn is logged to the `chat_logs` table (see api/chatbot.php).
 */
final class ChatBot
{
    /**
     * Intent keyword banks. ORDER MATTERS: for a given message, the first
     * *substantive* intent with a match wins, so specific intents
     * (github, linkedin, education…) are listed before generic ones.
     *
     * @var array<string, list<string>>
     */
    private const INTENTS = [
        'greeting'   => ['hi', 'hello', 'hey', 'yo', 'howdy', 'hi there', 'hello there', 'good morning', 'good afternoon', 'good evening', 'salam', 'assalam', 'greetings', 'how are you', 'how r u', 'howdy do', 'whats up', 'what s up', 'sup', 'wassup'],
        'thanks'     => ['thank you', 'thanks', 'thankyou', 'thx', 'cheers', 'jazak', 'appreciate it', 'nice work', 'great work', 'love it'],
        'bye'        => ['bye', 'goodbye', 'see you', 'see ya', 'later', 'take care', 'good night', 'goodnight', 'seeya'],
        'help'       => ['help', 'what can you do', 'what do you know', 'commands', 'options', 'capabilities', 'how does this work', 'how do i use', 'guide me', 'what questions', 'menu'],
        'github'     => ['github', 'git hub', 'your repos', 'repositories page', 'code hosted'],
        'linkedin'   => ['linkedin', 'linked in', 'professional profile', 'professional network'],
        'resume'     => ['resume', 'cv', 'curriculum vitae', 'download cv', 'download resume'],
        'education'  => ['education', 'educational', 'study', 'studies', 'studied', 'study at', 'school', 'university', 'college', 'degree', 'degree in', 'qualifica', 'graduat', 'alma mater', 'what did you study'],
        'location'   => ['location', 'located', 'where are you', 'where do you live', 'where are you from', 'which city', 'which country', 'live in', 'based in', 'your base', 'timezone'],
        'hire'       => ['hire', 'hiring', 'available', 'availability', 'freelance', 'freelancing', 'freelancer', 'job', 'jobs', 'internship', 'intern', 'open to work', 'opportunity', 'opportunities', 'work with you', 'collaborate', 'contract', 'do you take', 'for hire'],
        'contact'    => ['contact', 'email', 'e mail', 'reach you', 'reach out', 'get in touch', 'message you', 'write to you', 'phone', 'telephone', 'whatsapp', 'send you', 'call you', 'your contact'],
        'skills'     => ['skill', 'skills', 'technology', 'technologies', 'tech stack', 'stack', 'languages', 'language', 'framework', 'frameworks', 'libraries', 'tools', 'proficient', 'proficiency', 'expertise', 'good at', 'know how', 'what can you build'],
        'projects'   => ['project', 'projects', 'portfolio', 'repositories', 'repository', 'repos', 'repo', 'my work', 'your work', 'built', 'built projects', 'build', 'side project', 'show me', 'show your'],
        'interests'  => ['interest', 'interests', 'hobbies', 'hobby', 'like to do', 'free time', 'fun stuff', 'passion', 'what do you enjoy'],
        'experience' => ['experience', 'experiance', 'how long', 'years of', 'how many years', 'career history'],
        'price'      => ['price', 'pricing', 'cost', 'how much', 'charge', 'rates', 'rate', 'budget', 'expensive', 'afford', 'quote', 'payment'],
        'role'       => ['what do you do', 'your job', 'your role', 'occupation', 'profession', 'day job', 'what is your work', 'what do u do', 'do for work'],
        'bot'        => ['are you a bot', 'are you real', 'are you human', 'who made you', 'who created you', 'who built you', 'what are you', 'are you an ai', 'how do you work', 'chatbot what'],
        'about'      => ['about you', 'about yourself', 'about him', 'about her', 'about this site', 'who are you', 'your name', 'tell me about', 'your bio', 'introduce', 'tell me who', 'background story', 'a bit about'],
    ];

    /** Intents that only win when they're the ONLY thing in the message. */
    private const TRIVIAL = ['greeting', 'thanks', 'bye'];

    /** @var array<string, list<string>> */
    private const FALLBACK_SUGGESTIONS = [
        'What are your skills?',
        'Show me your projects',
        'How can I contact you?',
        'Are you available for hire?',
    ];

    public function answer(string $message, array $history = []): array
    {
        $message = trim($message);
        $intent  = $this->detectIntent($message);

        if ($intent !== null) {
            $local = $this->localAnswer($intent);
            if ($local !== null) {
                return [
                    'reply'       => $local['reply'],
                    'source'      => 'local',
                    'suggestions' => $local['suggestions'],
                ];
            }
        }

        // Free-form question → AI when configured and within the rate limit.
        if ($this->aiConfigured() && $this->aiAllowanceOk()) {
            try {
                $reply = $this->askAI($message, $history);
                if (is_string($reply) && trim($reply) !== '') {
                    return [
                        'reply'       => trim($reply),
                        'source'      => 'ai',
                        'suggestions' => self::FALLBACK_SUGGESTIONS,
                    ];
                }
            } catch (Throwable) {
                // fall through to the local fallback
            }
        }

        return [
            'reply'       => $this->fallbackReply(),
            'source'      => 'local',
            'suggestions' => self::FALLBACK_SUGGESTIONS,
        ];
    }

    /* ------------------------------------------------------------- intents */

    private function detectIntent(string $message): ?string
    {
        $normalized = strtolower($message);
        $normalized = preg_replace('/[^a-z0-9]+/', ' ', $normalized) ?? '';
        $normalized = ' ' . trim($normalized) . ' ';

        if (trim($normalized) === '') {
            return null;
        }

        $hits = [];
        foreach (self::INTENTS as $key => $keywords) {
            foreach ($keywords as $keyword) {
                $k = trim((string) (preg_replace('/[^a-z0-9]+/', ' ', strtolower($keyword)) ?? ''));
                if ($k !== '' && str_contains($normalized, ' ' . $k . ' ')) {
                    $hits[] = $key;
                    break;
                }
            }
        }

        if ($hits === []) {
            return null;
        }

        // A greeting/thanks/bye buried inside a real question shouldn't win.
        $substantive = array_values(array_filter(
            $hits,
            fn (string $h): bool => !in_array($h, self::TRIVIAL, true)
        ));

        return $substantive[0] ?? $hits[0];
    }

    private function localAnswer(string $intent): ?array
    {
        $name  = $this->name();
        $role  = (string) app_config('role', 'Developer');
        $email = (string) app_config('email', '');
        $gh    = $this->githubUrl();
        $li    = (string) app_config('linkedin_url', '');

        return match ($intent) {

            'greeting' => [
                'reply' => "Hey there! 👋 I'm the portfolio assistant for **{$name}** — {$role}. " .
                           "Ask me anything about skills, projects, education, availability, or how to get in touch!",
                'suggestions' => ['What are your skills?', 'Show me your projects', 'How can I contact you?'],
            ],

            'help' => [
                'reply' => "I can answer questions about:\n\n" .
                           "• **Skills & tech stack**\n" .
                           "• **Projects** (synced live from GitHub into MySQL)\n" .
                           "• **Education & interests**\n" .
                           "• **Contact details** — email, LinkedIn, GitHub\n" .
                           "• **Availability** for work and internships\n\n" .
                           "Just ask me naturally — like you'd ask a human!",
                'suggestions' => ['Tell me about yourself', 'What projects have you built?', 'Are you available for hire?'],
            ],

            'about' => [
                'reply' => "**{$name}** — {$role}.\n\n" .
                           $this->excerpt((string) app_config('bio', ''), 340) .
                           "\n\nScroll to the **About** section on this page for the full story!",
                'suggestions' => ['What are your skills?', 'Where are you based?', 'Where did you study?'],
            ],

            'role' => [
                'reply' => "**{$name}** works as a **{$role}**. " .
                           $this->excerpt((string) app_config('tagline', ''), 220),
                'suggestions' => ['What are your skills?', 'Show me your projects', 'How can I contact you?'],
            ],

            'skills' => [
                'reply' => $this->skillsReply(),
                'suggestions' => ['Show me your projects', 'Are you available for hire?', 'Where did you study?'],
            ],

            'projects' => [
                'reply' => $this->projectsReply(),
                'suggestions' => ['What are your skills?', 'How can I contact you?', 'Are you available for hire?'],
            ],

            'education' => [
                'reply' => $this->educationReply(),
                'suggestions' => ['What are your skills?', 'Show me your projects', 'How can I contact you?'],
            ],

            'location' => [
                'reply' => "**{$name}** is based in **" . $this->locationOr('an undisclosed location') . "**." .
                           ($gh !== '' ? " — and their code lives all over the world on [GitHub]({$gh})! 🌍" : ''),
                'suggestions' => ['What are your skills?', 'Show me your projects', 'Are you available for hire?'],
            ],

            'hire' => [
                'reply' => $this->hireReply(),
                'suggestions' => ['Show me your projects', 'How can I contact you?', 'What are your skills?'],
            ],

            'github' => [
                'reply' => $gh !== ''
                    ? "Everything public lives at [github.com/" . $this->githubUsername() . "]({$gh}). " .
                      "The **Projects** section of this page syncs straight from the GitHub API into MySQL — click **Sync now ↻** to pull the latest!"
                    : "The GitHub link hasn't been configured yet — the owner needs to add `github_username` in `config.php`.",
                'suggestions' => ['Show me your projects', 'What are your skills?', 'How can I contact you?'],
            ],

            'linkedin' => [
                'reply' => $li !== ''
                    ? "Sure — here's the LinkedIn profile: [{$li}]({$li}). " .
                      "You can also find GitHub and email under **Contact** on this page."
                    : "The LinkedIn URL hasn't been added yet — let the owner know to update `linkedin_url` in `config.php`.",
                'suggestions' => ['Show me your projects', 'How can I contact you?', 'Are you available for hire?'],
            ],

            'resume' => [
                'reply' => $this->resumeReply(),
                'suggestions' => ['Show me your projects', 'How can I contact you?'],
            ],

            'contact' => [
                'reply' => $this->contactReply(),
                'suggestions' => ['Show me your projects', 'Are you available for hire?', 'Tell me about yourself'],
            ],

            'interests' => [
                'reply' => $this->interestsReply(),
                'suggestions' => ['What are your skills?', 'Show me your projects', 'Where did you study?'],
            ],

            'experience' => [
                'reply' => "As a **{$role}**, the best evidence of hands-on experience is the **Projects** section — " .
                           "real repositories synced live from GitHub." .
                           ($li !== ''
                               ? " For a full career history and recommendations, check [LinkedIn]({$li})."
                               : ''),
                'suggestions' => ['Show me your projects', 'What are your skills?', 'How can I contact you?'],
            ],

            'price' => [
                'reply' => "Pricing depends on scope and timeline. The fastest way to get a real quote is to send the " .
                           "details through the **Contact** form on this page" .
                           ($email !== '' ? " or email [{$email}](mailto:{$email})" : '') .
                           " — you'll get a reply soon!",
                'suggestions' => ['Are you available for hire?', 'How can I contact you?'],
            ],

            'bot' => [
                'reply' => "I'm a hybrid chatbot built into this portfolio — a **PHP keyword engine** answers factual " .
                           "questions instantly from the site's own config and MySQL cache, and free-form questions can be " .
                           "forwarded to an **AI model** when the owner has configured an API key. No wizardry — just code in `src/ChatBot.php`!",
                'suggestions' => ['What can you do?', 'Show me your projects', 'Tell me about yourself'],
            ],

            'thanks' => [
                'reply' => "You're very welcome! 😊 Anything else I can help you with?",
                'suggestions' => ['Show me your projects', 'What are your skills?', 'How can I contact you?'],
            ],

            'bye' => [
                'reply' => "Bye! 👋 It was great chatting with you — feel free to come back anytime. Good luck with your own projects too!",
                'suggestions' => ['Show me your projects', 'Tell me about yourself'],
            ],

            default => null,
        };
    }

    /* ------------------------------------------------- answers with data */

    private function skillsReply(): string
    {
        $skills = (array) app_config('skills', []);
        $lines  = [];

        foreach ($skills as $group => $items) {
            if (!is_array($items) || $items === []) {
                continue;
            }
            $clean = array_values(array_filter(array_map('strval', $items)));
            if ($clean === []) {
                continue;
            }
            $lines[] = '• **' . (string) $group . ':** ' . implode(', ', $clean);
        }

        if ($lines === []) {
            return "The owner hasn't listed skills in `config.php` yet — check back soon!";
        }

        return "Here's the tech toolkit:\n\n" . implode("\n", $lines) . "\n\nWant to see it in action? Ask me about the **projects**!";
    }

    private function projectsReply(): string
    {
        $projects = $this->projects();

        if ($projects === []) {
            return "No projects are cached in MySQL yet. Open the **Projects** section and hit **Sync now ↻** to pull " .
                   "repositories from GitHub — then ask me again!";
        }

        $count = count($projects);
        $lines = [];

        foreach (array_slice($projects, 0, 4) as $p) {
            $lang    = $p['language'] ? ' — ' . $p['language'] : '';
            $stars   = $p['stars'] > 0 ? ' ★' . $p['stars'] : '';
            $desc    = $this->excerpt((string) ($p['description'] ?? ''), 90);
            $lines[] = "• **[" . $p['name'] . "]({$p['html_url']})**{$lang}{$stars}\n  {$desc}";
        }

        return "There are **{$count} project" . ($count === 1 ? '' : 's') . "** cached from GitHub, sorted by stars:\n\n" .
               implode("\n\n", $lines) . "\n\nSee the full grid in the **Projects** section!";
    }

    private function educationReply(): string
    {
        $education = (string) app_config('education', '');

        if ($education === '' || str_starts_with($education, 'Your Degree,')) {
            return "The owner hasn't published their education yet — but you can often see where they studied on " .
                   (!$this->isPlaceholderLinkedin() ? "their [LinkedIn]({$this->linkedinUrl()})" : 'their LinkedIn') . '.';
        }

        return "**{$this->name()}** studied at: **{$education}**.\n\n" .
               "Curious how it's put into practice? Check the **projects**!";
    }

    private function hireReply(): string
    {
        $status   = (array) app_config('status', []);
        $available = !empty($status['available']);
        $note      = trim((string) ($status['note'] ?? ''));
        $email     = (string) app_config('email', '');

        $lead = $available
            ? "**Yes! {$this->name()} is currently open to work.**"
            : "**{$this->name()} isn't actively looking at the moment**, but great opportunities are always worth hearing about.";

        if ($note !== '') {
            $lead .= " {$note}.";
        }

        return $lead . "\n\nStart a conversation through the **Contact** form on this page" .
               ($email !== '' ? " or email [{$email}](mailto:{$email})" : '') . '.';
    }

    private function contactReply(): string
    {
        $name   = $this->name();
        $email  = (string) app_config('email', '');
        $gh     = $this->githubUrl();
        $li     = $this->linkedinUrl();
        $resume = (string) app_config('resume_url', '');

        $lines = [];
        if ($email !== '') {
            $lines[] = "• Email: [{$email}](mailto:{$email})";
        }
        if ($li !== '') {
            $lines[] = "• LinkedIn: [Open profile]({$li})";
        }
        if ($gh !== '') {
            $lines[] = "• GitHub: [{$gh}]({$gh})";
        }
        if ($resume !== '') {
            $lines[] = "• Résumé: [Download]({$resume})";
        }

        $body = $lines !== []
            ? "Here's how to reach **{$name}**:\n\n" . implode("\n", $lines)
            : "The owner hasn't filled in contact details yet.";
        $body .= "\n\nYou can also use the **Contact** form on this page — messages go straight to the inbox!";

        return $body;
    }

    private function interestsReply(): string
    {
        $interests = array_values(array_filter(array_map('strval', (array) app_config('interests', []))));

        if ($interests === []) {
            return "Hmm, no interests listed yet — but building a full-stack portfolio like this one is clearly one of them! 😄";
        }

        return "Outside of writing code, **{$this->name()}** enjoys:\n\n• " .
               implode("\n• ", array_map(fn (string $i): string => '**' . $i . '**', $interests));
    }

    private function resumeReply(): string
    {
        $resume = (string) app_config('resume_url', '');

        if ($resume !== '') {
            return "Yes! Here's the link: [Download résumé]({$resume}) 📄";
        }

        return "A résumé link hasn't been added yet. Ask for one via [email](mailto:" . (string) app_config('email', '') .
               ") or the **Contact** form instead!";
    }

    private function fallbackReply(): string
    {
        $email = (string) app_config('email', '');

        return "Hmm, I don't have a ready-made answer for that one yet. 🤔 I'm best with questions about **skills**, " .
               "**projects**, **education**, **contact details** and **availability** — try one of those!" .
               ($email !== '' ? "\n\nFor anything else, you can write to **{$this->name()}** directly at [{$email}](mailto:{$email})." : '');
    }

    /* ------------------------------------------------------------------ ai */

    private function aiConfigured(): bool
    {
        return trim((string) app_config('ai.api_key', '')) !== '';
    }

    private function aiAllowanceOk(): bool
    {
        $limit = (int) app_config('ai.max_per_hour', 20);
        if ($limit <= 0) {
            return true;
        }

        $ip = $_SERVER['REMOTE_ADDR'] ?? '';
        if ($ip === '') {
            return true;
        }

        try {
            $pdo = Database::conn();
            $stmt = $pdo->prepare(
                'SELECT COUNT(*) FROM chat_logs
                 WHERE ip = ? AND source = ? AND created_at > (NOW() - INTERVAL 1 HOUR)'
            );
            $stmt->execute([$ip, 'ai']);
            return (int) $stmt->fetchColumn() < $limit;
        } catch (Throwable) {
            return true;
        }
    }

    private function askAI(string $message, array $history): ?string
    {
        $key = trim((string) app_config('ai.api_key', ''));
        if ($key === '') {
            return null;
        }

        $messages = [['role' => 'system', 'content' => $this->systemPrompt()]];

        foreach (array_slice($history, -8) as $turn) {
            if (!is_array($turn)) {
                continue;
            }
            $role    = (string) ($turn['role'] ?? '');
            $content = trim((string) ($turn['content'] ?? ''));
            if (!in_array($role, ['user', 'assistant'], true) || $content === '') {
                continue;
            }
            $messages[] = ['role' => $role, 'content' => mb_substr($content, 0, 2000)];
        }

        $messages[] = ['role' => 'user', 'content' => mb_substr($message, 0, 2000)];

        $payload = [
            'model'       => (string) app_config('ai.model', 'gpt-4o-mini'),
            'messages'    => $messages,
            'max_tokens'  => (int) app_config('ai.max_tokens', 400),
            'temperature' => 0.7,
        ];

        $ch = curl_init('https://api.openai.com/v1/chat/completions');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $key,
            ],
            CURLOPT_POSTFIELDS     => (string) json_encode($payload, JSON_UNESCAPED_SLASHES),
        ]);

        $response = curl_exec($ch);
        $status   = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $error    = curl_error($ch);
        curl_close($ch);

        if ($response === false) {
            throw new RuntimeException('AI request failed: ' . ($error ?: 'network error'));
        }

        if ($status !== 200) {
            $decoded = json_decode((string) $response, true);
            $msg = is_array($decoded) ? (string) ($decoded['error']['message'] ?? '') : '';
            throw new RuntimeException('AI request failed (HTTP ' . $status . ')' . ($msg !== '' ? ': ' . $msg : ''));
        }

        $decoded = json_decode((string) $response, true);
        $reply   = $decoded['choices'][0]['message']['content'] ?? null;

        return is_string($reply) ? $reply : null;
    }

    private function systemPrompt(): string
    {
        $name = $this->name();
        $lines = [
            "You are the friendly chat assistant embedded in {$name}'s personal portfolio website.",
            'Owner name: ' . $name,
            'Role: ' . (string) app_config('role', ''),
            'Location: ' . $this->locationOr('not disclosed'),
            'Email: ' . (string) app_config('email', ''),
            'GitHub: ' . $this->githubUrl(),
            'LinkedIn: ' . $this->linkedinUrl(),
            'Resume URL: ' . (string) app_config('resume_url', ''),
            'Bio: ' . (string) app_config('bio', ''),
            'Education: ' . (string) app_config('education', ''),
            'Interests: ' . json_encode(app_config('interests', []), JSON_UNESCAPED_UNICODE),
            'Skills: ' . json_encode(app_config('skills', []), JSON_UNESCAPED_UNICODE),
            'Availability: ' . (!empty(app_config('status.available')) ? 'Open to work' : 'Not currently hiring-related'),
            'Availability note: ' . (string) app_config('status.note', ''),
        ];

        $projects = $this->projects();
        if ($projects !== []) {
            $summary = array_map(
                static fn (array $p): string =>
                    $p['name'] . ' (' . ($p['language'] ?? 'n/a') . ', ★' . $p['stars'] . '): ' .
                    mb_substr((string) ($p['description'] ?? ''), 0, 120),
                array_slice($projects, 0, 10)
            );
            $lines[] = 'Top projects (cached from GitHub):' . "\n- " . implode("\n- ", $summary);
        }

        $lines[] = 'Rules: answer as a warm, helpful assistant speaking about the owner in third person (e.g. "they", "{name}"). ' .
                   'Keep answers to 2–5 sentences, or use a short bullet list when it genuinely helps. ' .
                   'USE ONLY facts from this prompt — never invent jobs, employers, awards, grades, contact details or skills. ' .
                   'If you do not know something, say so honestly and suggest the website\'s Contact form. ' .
                   'Reply in the user\'s language. ' .
                   'Plain text only — you may use **bold**, [label](https://url) markdown links and line breaks, but no headings or code blocks.';

        return implode("\n", $lines);
    }

    /* ------------------------------------------------------------ helpers */

    private function projects(): array
    {
        try {
            return (new ProjectStore(Database::conn()))->all();
        } catch (Throwable) {
            return [];
        }
    }

    private function name(): string
    {
        return (string) app_config('name', 'the owner');
    }

    private function githubUsername(): string
    {
        return (string) app_config('github_username', '');
    }

    private function githubUrl(): string
    {
        $u = trim($this->githubUsername());
        return ($u !== '' && strtolower($u) !== 'your-github-username')
            ? 'https://github.com/' . rawurlencode($u)
            : '';
    }

    private function linkedinUrl(): string
    {
        return (string) app_config('linkedin_url', '');
    }

    private function isPlaceholderLinkedin(): bool
    {
        return str_contains(strtolower($this->linkedinUrl()), 'your-handle');
    }

    private function locationOr(string $fallback): string
    {
        $loc = trim((string) app_config('location', ''));
        return $loc !== '' && !str_contains(strtolower($loc), 'your city') ? $loc : $fallback;
    }

    private function excerpt(string $text, int $limit): string
    {
        $text = trim((string) preg_replace('/\s+/', ' ', $text));

        if ($text === '') {
            return 'More details live in the **About** section of this site.';
        }

        $clean = trim(str_replace('Your Name', $this->name(), $text));
        $clean = str_replace('Your City, Country', $this->locationOr('their location'), $clean);

        if (mb_strlen($clean) <= $limit) {
            return $clean;
        }

        return rtrim(mb_substr($clean, 0, $limit), " \t.,;:") . '…';
    }
}