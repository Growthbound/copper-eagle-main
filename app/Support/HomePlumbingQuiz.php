<?php

namespace App\Support;

final class HomePlumbingQuiz
{
    public const SOURCES = [
        'ENERGY STAR water heater planning' => 'https://www.energystar.gov/sites/default/files/2025-01/HPWH_Toolkit_2024.pdf#page=29',
        'EPA WaterSense home maintenance' => 'https://www.epa.gov/watersense/home-maintenance',
        'USGS guide to water hardness' => 'https://www.usgs.gov/water-science-school/science/hardness-water',
    ];

    public const QUESTIONS = [
        'home' => [
            'topic' => 'Your home',
            'title' => 'When was your home built?',
            'hint' => 'Your best guess is fine.',
            'options' => ['older' => 'Before 1980', 'middle' => '1980–2009', 'newer' => '2010 or later', 'unknown' => 'I’m not sure'],
        ],
        'water-heater' => [
            'topic' => 'Water heater',
            'title' => 'What do you know about your water heater?',
            'hint' => 'A tank heater has a large storage tank. A tankless heater is usually mounted on the wall.',
            'options' => ['tank-under-6' => 'Tank, under 6 years old', 'tank-6-9' => 'Tank, 6–9 years old', 'tank-10-plus' => 'Tank, 10 years or older', 'tankless' => 'Tankless', 'unknown' => 'I’m not sure'],
        ],
        'hot-water' => [
            'topic' => 'Hot water',
            'title' => 'Have you noticed any hot water trouble?',
            'hint' => 'Pick the one you notice most often.',
            'options' => ['runs-out' => 'Hot water runs out sooner', 'temperature' => 'The temperature changes during use', 'none' => 'Hot water has been fine', 'unknown' => 'I’m not sure'],
        ],
        'drains' => [
            'topic' => 'Drains',
            'title' => 'How are your sinks, tubs, and showers draining?',
            'hint' => 'Think about the past few months.',
            'options' => ['repeated' => 'More than one drain is slow or gurgles', 'one' => 'One drain slows down now and then', 'none' => 'They’re draining normally', 'unknown' => 'I’m not sure'],
        ],
        'leaks' => [
            'topic' => 'Leaks',
            'title' => 'Have you noticed any signs of a leak?',
            'hint' => 'Pick the first one that applies.',
            'options' => ['moisture' => 'Damp spots or water where it shouldn’t be', 'running' => 'A running toilet or dripping fixture', 'bill' => 'A higher water bill I can’t explain', 'none' => 'Nothing that I’ve noticed', 'unknown' => 'I’m not sure'],
        ],
        'minerals' => [
            'topic' => 'Mineral buildup',
            'title' => 'Do you see chalky white buildup on your fixtures?',
            'hint' => 'Look for it around faucet tips, showerheads, or sink handles.',
            'options' => ['yes' => 'Yes, it builds up regularly', 'sometimes' => 'A little, once in a while', 'no' => 'Rarely or never', 'unknown' => 'I’m not sure'],
        ],
    ];

    /** @param array<string, string> $answers */
    public static function checklist(array $answers): array
    {
        $heater = $answers['water-heater'];
        $hotWater = in_array($answers['hot-water'], ['runs-out', 'temperature'], true);
        $leak = $answers['leaks'];
        $drains = $answers['drains'];
        $scale = in_array($answers['minerals'], ['yes', 'sometimes'], true);

        return [
            [
                'priority' => match (true) {
                    $hotWater, $heater === 'tank-10-plus' => 'Check soon',
                    $heater === 'tank-6-9' => 'Plan ahead',
                    default => 'Keep an eye on it',
                },
                'title' => match ($heater) {
                    'tank-10-plus', 'tank-6-9' => 'Plan for a new water heater',
                    'unknown' => 'Find your water heater’s age',
                    'tankless' => 'Check your tankless heater’s service history',
                    default => 'Keep tabs on your water heater',
                },
                'reason' => match (true) {
                    $hotWater => 'Changes in hot water are worth checking soon. Note where they happen and how long the hot water lasts.',
                    $heater === 'tank-10-plus' => 'With a tank this age, it’s worth talking through replacement options soon so you have time to plan.',
                    $heater === 'tank-6-9' => 'Your tank could cross the 10-year mark in the next 3–5 years. Start keeping track of its age and service history.',
                    default => 'Knowing your heater’s age and service history makes it easier to plan maintenance or replacement.',
                },
                'check' => $heater === 'tankless'
                    ? 'Use the model number on the outside label to find the maker’s maintenance schedule. Check when it was last serviced.'
                    : 'Look for an install date on the outside label. If you only see a serial number, look it up with the maker. Check the floor around the heater for moisture.',
            ],
            [
                'priority' => in_array($drains, ['repeated', 'one'], true) ? 'Check soon' : 'Keep an eye on it',
                'title' => $drains === 'repeated' ? 'Recurring slow drains' : 'Watch for slow drains',
                'reason' => match ($drains) {
                    'repeated' => 'When several fixtures slow down together, let a plumber know which ones are affected.',
                    'one' => 'If the same drain keeps slowing down, keep track of how often it happens.',
                    default => 'Knowing how quickly your drains normally clear makes changes easier to spot.',
                },
                'check' => 'Note which fixtures drain slowly or gurgle during normal use and whether they do it at the same time.',
            ],
            [
                'priority' => in_array($leak, ['moisture', 'running', 'bill'], true) ? 'Check soon' : 'Keep an eye on it',
                'title' => match ($leak) {
                    'moisture' => 'Check the damp spot soon',
                    'running' => 'Check for fixture leaks',
                    'bill' => 'Check for hidden water use',
                    default => 'Look for small leaks',
                },
                'reason' => match (true) {
                    $leak === 'moisture' => 'If water is pooling or a wet spot is spreading, give us a call so we can help you find the source.',
                    $leak === 'bill' => 'An unexplained jump in water use is a good reason to check for a leak.',
                    $answers['home'] === 'older' => 'If you’re unsure when the plumbing was updated, make a note of visible corrosion, damp spots, and any replacement records you have.',
                    default => 'A quick look under sinks and around toilets can help you spot small leaks early.',
                },
                'check' => match ($leak) {
                    'moisture' => 'If you can see the spot safely, take a photo and note when it gets wet.',
                    'bill' => 'If your water meter is easy to access, compare two readings two hours apart while no water is being used. A change can point to a leak.',
                    default => 'For a tank toilet, add a few drops of food coloring to the tank. If color reaches the bowl within 10 minutes without flushing, there’s a leak. Flush afterward.',
                },
            ],
            [
                'priority' => $scale ? 'Plan ahead' : 'Keep an eye on it',
                'title' => 'Keep an eye on mineral buildup',
                'reason' => $scale
                    ? 'The chalky deposits you noticed could be mineral scale. Scale can build up inside fixtures and water heaters too.'
                    : 'If you start seeing chalky deposits, keep track of where they show up and how quickly they return.',
                'check' => 'Check the tips of faucets and showerheads for white crust. Ask your water provider for its hardness report if you see buildup.',
            ],
        ];
    }

    /** @param array<string, string> $answers */
    public static function leadMessage(array $answers, string $inspection): string
    {
        $lines = ['Home plumbing quiz', 'Inspection: '.$inspection];
        foreach (self::QUESTIONS as $key => $question) {
            $lines[] = $question['topic'].': '.$question['options'][$answers[$key]];
        }

        return implode("\n", $lines);
    }
}
