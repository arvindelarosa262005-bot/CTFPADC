<?php

return [
    'categories' => [
        'web-exploitation' => 'Web Exploitation',
        'cryptography' => 'Cryptography',
        'digital-forensics' => 'Digital Forensics',
        'steganography' => 'Steganography',
        'networking' => 'Networking',
        'osint' => 'OSINT',
        'reverse-engineering' => 'Reverse Engineering',
        'binary-exploitation' => 'Binary Exploitation',
        'miscellaneous' => 'Miscellaneous',
    ],
    'difficulties' => [
        'beginner' => 'Beginner',
        'intermediate' => 'Intermediate',
        'medium' => 'Medium',
        'advanced' => 'Advanced',
        'easy' => 'Easy',
        'hard' => 'Hard',
    ],
    'flag_pattern' => '/\AP4DC\{[^{}\r\n]+\}\z/',
    'team_scoring_policy' => 'A verified challenge awards its points once to the solver and, if they belong to a team at solve time, once to that team.',
    'leaderboard_tie_break' => 'username_or_team_name_ascending',
];
