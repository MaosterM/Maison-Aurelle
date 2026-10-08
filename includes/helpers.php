<?php
function e($s): string { return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function money($n): string { return (CURRENCY === 'USD' ? '$' : CURRENCY . ' ') . number_format((float)$n, 0); }
