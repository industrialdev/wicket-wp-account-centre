<?php

// Prepend autoloader: resolves the pre-0.8 OrgManagement\OrgMan namespace to
// WicketORM\OrgMan. Loading WicketORM\OrgMan triggers the class_alias at the
// bottom of OrgMan.php, so PHP finds the class after this handler returns.
spl_autoload_register(static function (string $class): void {
    if ($class === 'OrgManagement\\OrgMan') {
        class_exists('WicketORM\\OrgMan', true);
    }
}, true, true);
