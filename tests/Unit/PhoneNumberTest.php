<?php

use App\Enums\ContactMethodType;
use App\Models\ContactMethod;
use App\Support\PhoneNumber;

it('normalises Nepali numbers to E.164 in any common format', function (string $input) {
    expect(PhoneNumber::toE164($input))->toBe('+9779801011111');
})->with(['9801011111', '+977 980-1011111', '+977-9801011111', '00977 9801011111', '(980) 101-1111']);

it('keeps the digits when a number cannot be parsed', function () {
    expect(PhoneNumber::toE164('12'))->toBeNull()
        ->and(PhoneNumber::normalize('12-3'))->toBe('123');
});

it('normalises emails and handles for matching', function () {
    expect(ContactMethod::normalizeValue('  Sita.Sharma@Mail.Example ', ContactMethodType::Email))->toBe('sita.sharma@mail.example')
        ->and(ContactMethod::normalizeValue('@Dawa.Treks', ContactMethodType::Instagram))->toBe('dawa.treks');
});

it('guesses the type of a contact value', function () {
    expect(ContactMethod::guessType('sita@everest.example'))->toBe(ContactMethodType::Email)
        ->and(ContactMethod::guessType('@dawa.treks'))->toBe(ContactMethodType::Instagram)
        ->and(ContactMethod::guessType('+977 980-1011111'))->toBe(ContactMethodType::Phone);
});
