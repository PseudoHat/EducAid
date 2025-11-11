<?php
/**
 * Bulk update all history endpoints to include admin_username and summary
 */

$files = [
    'ajax_get_about_history.php',
    'ajax_get_hiw_history.php',
    'ajax_get_contact_history.php',
    'ajax_get_req_history.php',
    'ajax_get_ann_history.php'
];

$tables = [
    'ajax_get_about_history.php' => 'about_content_audit',
    'ajax_get_hiw_history.php' => 'hiw_content_audit',
    'ajax_get_contact_history.php' => 'contact_content_audit',
    'ajax_get_req_history.php' => 'req_content_audit',
    'ajax_get_ann_history.php' => 'ann_content_audit'
];

foreach ($files as $file) {
    $path = __DIR__ . '/website/' . $file;
    
    if (!file_exists($path)) {
        echo "⚠️  Skip: $file (not found)\n";
        continue;
    }
    
    $content = file_get_contents($path);
    $table = $tables[$file];
    
    // Check if already updated
    if (strpos($content, 'admin_username') !== false) {
        echo "✅ Already updated: $file\n";
        continue;
    }
    
    // Update SELECT to include admin_username
    $old_select = "SELECT audit_id, block_key, action_type, created_at, new_html, old_html, new_text_color, old_text_color, new_bg_color, old_bg_color FROM {$table}";
    $new_select = "SELECT audit_id, block_key, action_type, created_at, new_html, old_html, new_text_color, old_text_color, new_bg_color, old_bg_color, admin_username FROM {$table}";
    
    $content = str_replace($old_select, $new_select, $content);
    
    // Add summary function and include in records (for minified files, add before the records array creation)
    $summary_function = "function gen_sum(\$row){\$a=\$row['action_type'];\$c=[];if(\$a==='update'){\$ot=strip_tags(\$row['old_html']??'');\$nt=strip_tags(\$row['new_html']??'');if(\$ot!==\$nt){\$ow=str_word_count(\$ot);\$nw=str_word_count(\$nt);\$d=\$nw-\$ow;if(\$d>0)\$c[]=\"Added \$d words\";elseif(\$d<0)\$c[]=\"Removed \".abs(\$d).\" words\";else \$c[]=\"Modified text\";}if((\$row['old_text_color']??'')!==(\$row['new_text_color']??' '))\$c[]=\"Changed text color\";if((\$row['old_bg_color']??'')!==(\$row['new_bg_color']??''))\$c[]=\"Changed background\";return !empty(\$c)?implode(', ',\$c):'Updated content';}if(\$a==='reset_all')return 'Reset all content to default';if(\$a==='rollback')return 'Rolled back to previous version';return 'Modified';}";
    
    // Add function before $clauses (look for the clauses array initialization)
    $content = preg_replace(
        "/(\\\$clauses=\['municipality_id=1'\];)/",
        $summary_function . "\n$1",
        $content
    );
    
    // Update records array to include admin_username and summary
    $content = preg_replace(
        "/'html'=>s\(\\\$content\),'text_color'=>\\\$text_color,'bg_color'=>\\\$bg_color\]/",
        "'html'=>s(\$content),'text_color'=>\$text_color,'bg_color'=>\$bg_color,'admin_username'=>\$row['admin_username']??'System','summary'=>gen_sum(\$row)]",
        $content
    );
    
    file_put_contents($path, $content);
    echo "✅ Updated: $file\n";
}

echo "\n✅ All history endpoints updated!\n";
?>
