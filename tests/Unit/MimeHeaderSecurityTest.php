<?php

declare(strict_types=1);

use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\Exception\RfcComplianceException;
use Symfony\Component\Mime\Header\ParameterizedHeader;

test('MIME parameter construction rejects header injection in parameter names', function () {
    expect(fn () => new ParameterizedHeader('Content-Disposition', 'attachment', [
        "filename\r\nX-IntelLedger-Probe" => 'synthetic.csv',
    ]))->toThrow(RfcComplianceException::class);
});

test('MIME parameter mutations reject non-token names without changing accepted parameters', function (string $operation, string $name) {
    $header = new ParameterizedHeader('Content-Disposition', 'attachment', ['filename' => 'synthetic.csv']);

    try {
        if ($operation === 'single') {
            $header->setParameter($name, 'synthetic-only');
        } else {
            $header->setParameters(['filename' => 'replacement.csv', $name => 'synthetic-only']);
        }
        $this->fail('Non-token MIME parameter names must be rejected.');
    } catch (RfcComplianceException) {
        expect($header->getParameters())->toBe(['filename' => 'synthetic.csv']);
    }
})->with([
    'CRLF single setter' => ['single', "filename\r\nX-IntelLedger-Probe"],
    'CRLF bulk setter' => ['bulk', "filename\r\nX-IntelLedger-Probe"],
    'empty name' => ['bulk', ''],
    'separator in name' => ['bulk', 'file;name'],
    'whitespace in name' => ['bulk', 'file name'],
]);

test('valid MIME parameters retain quoted values and normal email serialization works without transport', function () {
    $header = new ParameterizedHeader('Content-Disposition', 'attachment', ['filename' => 'Synthetic "statement".csv']);

    expect($header->getParameter('filename'))->toBe('Synthetic "statement".csv');
    expect($header->toString())->toContain('filename="Synthetic \\"statement\\".csv"');

    $email = (new Email)
        ->from('sender@example.test')
        ->to('recipient@example.test')
        ->subject('Synthetic notification')
        ->text('Synthetic notification body')
        ->attach('Synthetic attachment bytes', 'synthetic.csv', 'text/csv');

    $serialized = $email->toString();

    expect($serialized)
        ->toContain('Subject: Synthetic notification', 'Synthetic notification body', 'Content-Type: text/csv', 'filename=synthetic.csv')
        ->not->toContain('X-IntelLedger-Probe:');
});
