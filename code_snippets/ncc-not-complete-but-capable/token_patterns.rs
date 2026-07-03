// shiki: nolinenum
const TOKEN_PATTERNS: &[(&str, Token)] = &[
    (r"^int\b", Token::IntKeyword),
    (r"^return\b", Token::ReturnKeyword),
    (r"^[a-zA-Z_]\w*\b", Token::Identifier(String::new())),
    (r"^[0-9]+\b", Token::ConstantInt(String::new())),
    // ... operators, punctuation, etc.
];
