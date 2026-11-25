<?php

use App\Enums\BloodGroup;

test('blood group accepts the form label and the stored value', function (string $input, BloodGroup $expected) {
    expect(BloodGroup::parse($input))->toBe($expected);
})->with([
    'A+' => ['A+', BloodGroup::APositive],
    'A-' => ['A-', BloodGroup::ANegative],
    'A unicode minus' => ["A\u{2212}", BloodGroup::ANegative],
    'B+' => ['B+', BloodGroup::BPositive],
    'B-' => ['B-', BloodGroup::BNegative],
    'AB+' => ['AB+', BloodGroup::AbPositive],
    'AB-' => ['AB-', BloodGroup::AbNegative],
    'O+' => ['O+', BloodGroup::OPositive],
    'O unicode minus' => ["O\u{2212}", BloodGroup::ONegative],
    'spaced positive' => ['O positive', BloodGroup::OPositive],
    'stored value' => ['o_positive', BloodGroup::OPositive],
    'Unknown' => ['Unknown', BloodGroup::Unknown],
]);

test('blood group rejects a value outside the form list', function () {
    expect(BloodGroup::parse('C+'))->toBeNull();
});
