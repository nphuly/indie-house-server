<?php

// Terms of use and privacy policy shown at /terms and /privacy. The operator
// of each instance answers for these texts: when a deployment stores or
// shares data differently, edit both languages (docs/en/privacy.md).
// `:instance` is replaced with INSTANCE_NAME.
return [
    'updated' => 'Last updated: 4 October 2026',
    'terms_link' => 'Terms',
    'privacy_link' => 'Privacy',
    'consent' => 'By creating an account, you agree to the <a href=":terms">Terms of use</a> and confirm you have read the <a href=":privacy">Privacy policy</a>.',
    'contact_title' => 'Contact',
    'contact_email' => 'Send questions, personal data requests or copyright complaints to the operator of :instance at',
    'contact_admin' => 'Send questions, personal data requests or copyright complaints to the operator of :instance, who invited you.',

    'terms' => [
        'title' => 'Terms of use',
        'intro' => ':instance is a small music streaming service that one person runs for the people they invite, using the Indie House software, whose source code is publicly available. By using :instance, you agree to these terms.',
        'sections' => [
            [
                'title' => 'A non-commercial service',
                'paragraphs' => [
                    ':instance sells no plans, charges no fees and shows no ads. The operator pays for the server and makes no money from the service or from your data.',
                ],
            ],
            [
                'title' => 'Your account',
                'items' => [
                    'An account is for one person. Do not share your password, your invite code or a signed-in device.',
                    'You are responsible for what happens in your account. If you think it has been exposed, change your password and revoke devices in Settings.',
                    'The operator may suspend or delete accounts that break these terms.',
                ],
            ],
            [
                'title' => 'Permitted use',
                'paragraphs' => [
                    'You may listen to music on :instance for personal, non-commercial purposes. You may not:',
                ],
                'items' => [
                    'download, record or redistribute music without the rights holder’s permission;',
                    'play music from :instance in a business, at a public event or for any commercial purpose;',
                    'scrape the service, get around its rate limits or access controls, or attack it;',
                    'use the service in a way that breaks the law or infringes anyone’s rights.',
                ],
            ],
            [
                'title' => 'Content and copyright',
                'paragraphs' => [
                    'Music, artwork and artist information belong to their rights holders. The operator uploads the content on :instance and is responsible for holding the rights or permission for every track. Your account gives you no rights to the content beyond listening on the service.',
                    'If you hold the rights to content on :instance and believe it infringes them, tell the operator using the Contact section. Content that is the subject of a valid complaint will be removed.',
                ],
            ],
            [
                'title' => 'No warranty',
                'paragraphs' => [
                    ':instance runs on a personal server and is provided as is. The service may be interrupted, changed or shut down at any time, and data may be lost even with backups. To the extent permitted by law, neither the operator nor the contributors to Indie House are liable for any damage arising from use of the service.',
                ],
            ],
            [
                'title' => 'Source code and self-hosting',
                'paragraphs' => [
                    'Indie House\'s source code is published under the PolyForm Noncommercial License 1.0.0, without any warranty. Its authors and contributors do not run other instances and do not provide, host or review the music on them.',
                    'Anyone who runs an instance is solely responsible for the security of their server and data, for complying with the law where they operate, and for all content they or their users upload, including music they do not hold the rights to. The project does not support distributing pirated music and is not intended for commercial use.',
                ],
            ],
            [
                'title' => 'Changes to these terms',
                'paragraphs' => [
                    'These terms may change. The date at the top of the page shows the current version; using :instance after that date means you accept it.',
                ],
            ],
        ],
    ],

    'privacy' => [
        'title' => 'Privacy policy',
        'intro' => ':instance keeps only the data needed to sign you in, play music and keep your account safe. It is never sold, rented, traded or used for advertising, and it is not used for any commercial purpose.',
        'sections' => [
            [
                'title' => 'Who holds your data',
                'paragraphs' => [
                    'The operator of :instance keeps the data on their own server and storage. The authors of the Indie House software receive no data from this instance.',
                ],
            ],
            [
                'title' => 'What is stored',
                'items' => [
                    ['label' => 'Account', 'text' => 'display name, email, password (stored only as a hash), profile picture if you upload one, and when the account was created.'],
                    ['label' => 'Preferences', 'text' => 'playback quality, autoplay, and whether listening history is recorded.'],
                    ['label' => 'Library', 'text' => 'the playlists you create, with their title, description, visibility and tracks.'],
                    ['label' => 'Listening history', 'text' => 'the track, seconds played, whether you finished it, and when, without your IP address or browser details. You can turn it off in Settings.'],
                    ['label' => 'App sign-ins', 'text' => 'device name, operating system, IP address, user agent, last use and session expiry. Sign-in tokens are stored only as hashes.'],
                    ['label' => 'Security log', 'text' => 'app sign-ins, token refreshes and revoked sessions, with the IP address and device.'],
                    ['label' => 'Invites', 'text' => 'your email if the invite was addressed to it, and the IP address you used to redeem the invite.'],
                ],
            ],
            [
                'title' => 'Temporary data and logs',
                'items' => [
                    'Web sessions are kept on the server and expire after a period of inactivity the operator sets (2 hours by default).',
                    'Sign-in rate limit counters use hashed emails and IP addresses and expire after a few minutes.',
                    'The web server logs no IP addresses, paths or cookies. The audio gateway logs the account ID, track, response times and the first 100 characters of the user agent, but no IP address.',
                    'Logs are rotated; each service keeps only about the last 50 MB.',
                ],
            ],
            [
                'title' => 'Cookies and on-device storage',
                'paragraphs' => [
                    'Only what the service needs to work, with no advertising or tracking cookies:',
                ],
                'items' => [
                    'a session cookie and a cookie that protects forms against cross-site request forgery (CSRF);',
                    'a cookie with the language you chose;',
                    'a remember-me cookie, only if you tick “Remember me”;',
                    'the track you were playing, kept in your browser (localStorage) to resume playback and never sent to the server;',
                    'the iOS and Android apps keep your sign-in tokens (in the Keychain on iOS) and app settings on the device.',
                ],
            ],
            [
                'title' => 'How it is used',
                'paragraphs' => [
                    'The data is used only to sign you in, play music at the quality you choose, show your library, playlists and Recently played, count plays per track, detect abuse, protect accounts and restore the service after a failure. There is no behavioural analytics, no advertising profile and no sharing with third parties.',
                ],
            ],
            [
                'title' => 'Third parties',
                'items' => [
                    'Cloudflare forwards HTTPS connections to :instance, so it sees your IP address and traffic under Cloudflare’s own policies.',
                    'Music and images are kept in S3-compatible storage chosen by the operator.',
                    'The listening pages load their fonts from :instance itself. They embed no analytics, ad networks or social media buttons.',
                ],
            ],
            [
                'title' => 'How long it is kept',
                'paragraphs' => [
                    'Account data, playlists and listening history are kept until the account is deleted; history is not yet deleted automatically after a set time. App sessions expire after a period the operator sets (30 days by default). Backups contain this data, are encrypted with AES-256 and are kept according to the clean-up schedule the operator configures.',
                ],
            ],
            [
                'title' => 'Your choices',
                'items' => [
                    'View and edit your profile, change your password and revoke devices in Settings.',
                    'Turn listening history off at any time.',
                    'Download your account data, playlists and listening history as JSON in Settings.',
                    'Ask the operator to delete your account and data, using the Contact section.',
                ],
            ],
            [
                'title' => 'Security',
                'paragraphs' => [
                    'Connections use HTTPS, passwords and tokens are stored only as hashes, sign-ins are rate limited, changing your password signs out your other sessions, and backups are encrypted. No system is perfectly secure; if you find a vulnerability, please tell the operator.',
                ],
            ],
            [
                'title' => 'Changes to this policy',
                'paragraphs' => [
                    'This policy is updated whenever the service changes how it stores or uses data. The date at the top of the page shows the current version.',
                ],
            ],
        ],
    ],
];
