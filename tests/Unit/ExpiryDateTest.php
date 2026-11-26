<?php

declare(strict_types=1);

use AthenaNetworks\AcmaSenderId\Data\Chatbots\ChatbotInfo;
use AthenaNetworks\AcmaSenderId\Data\Chatbots\ChatbotSummary;
use AthenaNetworks\AcmaSenderId\Support\ExpiryDate;

// Behaviour observed against the ACMA test tenant (see ExpiryDate docblock).

it('formats dates in the only form ACMA accepts', function (DateTimeInterface|string $input, string $expected) {
    expect(ExpiryDate::format($input))->toBe($expected);
})->with([
    'UTC datetime' => [new DateTimeImmutable('2027-01-25 13:45:30', new DateTimeZone('UTC')), '2027-01-25T13:45:30.0000000Z'],
    'offset datetime' => [new DateTimeImmutable('2027-01-25T10:00:00+10:00'), '2027-01-25T00:00:00.0000000Z'],
    'string with offset' => ['2027-01-25T10:00:00+10:00', '2027-01-25T00:00:00.0000000Z'],
    'date-only string is Sydney midnight (AEDT)' => ['2027-01-31', '2027-01-30T13:00:00.0000000Z'],
    'date-only string is Sydney midnight (AEST)' => ['2027-06-30', '2027-06-29T14:00:00.0000000Z'],
    'drops sub-second precision beyond 6 digits' => [new DateTimeImmutable('2027-01-25 13:45:30.123456', new DateTimeZone('UTC')), '2027-01-25T13:45:30.1234560Z'],
]);

it('parses detail responses as Sydney local time', function () {
    $date = ExpiryDate::parseResponse('2027-01-26T00:45:30.0000000');

    expect($date?->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s'))->toBe('2027-01-25 13:45:30');
});

it('ignores the misleading Z on list responses', function () {
    $summary = ChatbotSummary::fromArray(['ChatbotId' => 'c1', 'x-ACMA-expiresOn' => '2027-01-26T00:45:30Z']);

    expect($summary->expiresOn?->getTimezone()->getName())->toBe('Australia/Sydney')
        ->and($summary->expiresOn?->setTimezone(new DateTimeZone('UTC'))->format('c'))->toBe('2027-01-25T13:45:30+00:00');
});

it('round-trips through ChatbotInfo', function () {
    $sent = new ChatbotInfo(expiresOn: new DateTimeImmutable('2027-01-25T13:45:30Z'));
    $received = ChatbotInfo::fromArray(['x-ACMA-expiresOn' => '2027-01-26T00:45:30.0000000']);

    expect($sent->toArray()['x-ACMA-expiresOn'])->toBe('2027-01-25T13:45:30.0000000Z')
        ->and($received->expiresOn()?->getTimestamp())->toBe($sent->expiresOn()?->getTimestamp());
});

it('returns null for empty or invalid values', function () {
    expect(ExpiryDate::parseResponse(null))->toBeNull()
        ->and(ExpiryDate::parseResponse(''))->toBeNull()
        ->and(ExpiryDate::parseResponse('garbage'))->toBeNull()
        ->and(ExpiryDate::format('garbage'))->toBeNull();
});
