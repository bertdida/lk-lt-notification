<?php
use GetOpt\Argument;
use GetOpt\ArgumentException;
use GetOpt\GetOpt;
use GetOpt\Option;
use LTN\Utils\Job;

$getOpt = new GetOpt();
$getOpt->addOptions([
    Option::create(null, 'userid', GetOpt::REQUIRED_ARGUMENT)
        ->setDescription('The users LeadKlozer id')
        ->setArgument(new Argument(null, 'is_numeric', 'userid')),

    Option::create(null, 'ishourly', GetOpt::NO_ARGUMENT)
        ->setDescription('If set hourly engagements will be summarized, otherwise daily'),
]);

try {
    $getOpt->process();
} catch (ArgumentException $exception) {
    echo PHP_EOL . $getOpt->getHelpText();
    exit;
}

$userId = (int) $getOpt->getOption('userid');
$isHourly = $getOpt->getOption('ishourly') !== null;
(new Job($userId, $isHourly))->run();
