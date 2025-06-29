<?php
function simple_markdown_to_html(string $markdown): string {
    // Normalize line endings
    $markdown = str_replace(["\r\n", "\r"], "\n", $markdown);

    // Escape HTML
    $markdown = htmlspecialchars($markdown, ENT_QUOTES, 'UTF-8');

    // Code blocks ```code```
    $markdown = preg_replace_callback('/```\n?(.*?)\n?```/s', function($m) {
        return '<pre><code>' . $m[1] . '</code></pre>';
    }, $markdown);

    // Inline code `code`
    $markdown = preg_replace('/`([^`]+)`/', '<code>$1</code>', $markdown);

    // Headings #, ##, etc.
    for ($i = 6; $i >= 1; $i--) {
        $pattern = '/^' . str_repeat('#', $i) . ' (.+)$/m';
        $replacement = '<h' . $i . '>$1</h' . $i . '>';
        $markdown = preg_replace($pattern, $replacement, $markdown);
    }

    // Bold **text**
    $markdown = preg_replace('/\*\*(.+?)\*\*/s', '<strong>$1</strong>', $markdown);
    // Italic *text*
    $markdown = preg_replace('/\*(.+?)\*/s', '<em>$1</em>', $markdown);

    // Lists starting with - or *
    $markdown = preg_replace_callback('/(?:^[-*] .+(?:\n|$))+/m', function($m) {
        $lines = array_filter(array_map('trim', explode("\n", trim($m[0]))));
        $html = "<ul>";
        foreach ($lines as $line) {
            $html .= '<li>' . preg_replace('/^[-*]\s+/', '', $line) . '</li>';
        }
        $html .= '</ul>';
        return $html;
    }, $markdown);

    // Paragraphs
    $paragraphs = preg_split('/\n{2,}/', $markdown);
    foreach ($paragraphs as &$para) {
        if (!preg_match('/^\s*<(?:h[1-6]|ul|pre|blockquote|\/?p)/', $para)) {
            $para = '<p>' . trim($para) . '</p>';
        }
    }
    return implode("\n", $paragraphs);
}
?>
