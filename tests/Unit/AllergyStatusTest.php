<?php

use App\Enums\AllergyStatus;

test('allergy status accepts the form label and the stored value', function (string $input, AllergyStatus $expected) {
    expect(AllergyStatus::parse($input))->toBe($expected);
})->with([
    'Known allergies' => ['Known allergies', AllergyStatus::KnownAllergies],
    'None known' => ['None known', AllergyStatus::NoneKnown],
    'Unknown' => ['Unknown', AllergyStatus::Unknown],
    'stored known' => ['known_allergies', AllergyStatus::KnownAllergies],
    'stored none' => ['none_known', AllergyStatus::NoneKnown],
    'stored unknown' => ['unknown', AllergyStatus::Unknown],
]);

test('allergy status rejects a value outside the form buttons', function () {
    expect(AllergyStatus::parse('severe'))->toBeNull();
});
