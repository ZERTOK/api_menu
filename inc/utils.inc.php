<?php
function showError($message)
{
    echo '<h2>Error</h2>';
    echo nl2br(htmlspecialchars($message));
    echo '</body>';
    echo '</html>';
    exit();
}

function getTime()
{
    date_default_timezone_set('Europe/Zurich');
    return date('h:i:s');
}

?>