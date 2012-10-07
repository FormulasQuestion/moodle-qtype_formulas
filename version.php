<?php
defined('MOODLE_INTERNAL') || die();

$plugin->component = 'qtype_formulas';
$plugin->version   = 2012071400;

$plugin->cron      = 0;
$plugin->requires  = 2012061700;
$plugin->dependencies = array(
    'qbehaviour_adaptivemultipart'     => 2012070200,
);

$plugin->maturity  = MATURITY_STABLE;

