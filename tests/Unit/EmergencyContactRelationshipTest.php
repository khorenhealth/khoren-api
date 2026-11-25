<?php

use App\Enums\EmergencyContactRelationship;

test('emergency contact relationship accepts the form label and the stored value', function (string $input, EmergencyContactRelationship $expected) {
    expect(EmergencyContactRelationship::parse($input))->toBe($expected);
})->with([
    'Spouse' => ['Spouse', EmergencyContactRelationship::Spouse],
    'Parent' => ['Parent', EmergencyContactRelationship::Parent],
    'Sibling' => ['Sibling', EmergencyContactRelationship::Sibling],
    'Friend' => ['Friend', EmergencyContactRelationship::Friend],
    'Carer' => ['Carer', EmergencyContactRelationship::Carer],
    'Other' => ['Other', EmergencyContactRelationship::Other],
    'stored value' => ['spouse', EmergencyContactRelationship::Spouse],
]);

test('emergency contact relationship rejects a value outside the form list', function () {
    expect(EmergencyContactRelationship::parse('Cousin'))->toBeNull();
});
