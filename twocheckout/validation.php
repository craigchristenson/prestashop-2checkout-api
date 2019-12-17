<?php

include(dirname(__FILE__).'/../../config/config.inc.php');
include(dirname(__FILE__).'/../../init.php');
include(dirname(__FILE__).'/twocheckout.php');

if (!defined('_PS_VERSION_'))
    exit;

$twocheckout = new Twocheckout();
if ($twocheckout->active && isset($_POST['token']))
    $twocheckout->processPayment($_POST['token']);
else
    die('You must submit a valid token to use the 2Checkout Payment API.');
