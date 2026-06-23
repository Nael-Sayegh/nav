<?php

declare(strict_types=1);

if (isset($_COOKIE['audio']) && ($_COOKIE['audio'] === '10' || preg_match('#[1-9]#', (string) $_COOKIE['audio'])))
{
    echo '<audio id="007t007x" src="'.$sound_path.'" autoplay></audio>';
    echo '<script type="text/javascript">document.getElementById("007t007x").volume = ';
    if ($_COOKIE['audio'] === '10')
    {
        echo '1';
    }
    else
    {
        echo '0.'.$_COOKIE['audio'];
    }

    echo ';</script>';
}
