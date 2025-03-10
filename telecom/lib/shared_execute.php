<?php
function ERROR($message, $level = 0) {
    error_log("ERROR (Level $level): $message");
}
?>