<?php
/** Log out. */
require __DIR__ . '/app/bootstrap.php';

logout_user();
flash('info', 'You have been logged out.');
redirect('index.php');
