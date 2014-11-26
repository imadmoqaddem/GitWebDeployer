<?php

require('../src/GitWebDeployer/Git.php');

//use GitWebDeployer;

$config = array();
$git = new GitWebDeployer\Git($config);

$git->test();