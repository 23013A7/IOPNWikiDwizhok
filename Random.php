<?php
$Lines = file("Page/index.txt", FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
$Page = $Lines[array_rand($Lines)];
echo "<meta http-equiv=\"refresh\" content=\"0; url=.?Page=$Page\">";
?>