<?php
namespace BizCuit\SwissQR;

use \stdClass;

/* QR Standard according to SIX Group, publishing implementation guideline for
 * the Swiss QR Bill. Follows version 2.2. 
 * https://www.six-group.com/dam/download/banking-services/standardization/qr-bill/ig-qr-bill-v2.2-fr.pdf
 */
$SWISS_QRSTD = [
    '0200' => [
        '_RESERVED' => [11, 12, 13, 14, 15, 16, 17],
        'CODING' => ['line' => 2],
        'IBAN' => ['line' => 3],
        'ADDR_CREDITOR_TYPE' => ['line' => 4],
        'ADDR_CREDITOR_NAME' => ['line' => 5],
        'ADDR_CREDITOR_STREET_OR_LINE1' => ['line' => 6],
        'ADDR_CREDITOR_HOUSE_OR_LINE2' => ['line' => 7],
        'ADDR_CREDITOR_NPA' => ['line' => 8],
        'ADDR_CREDITOR_CITY' => ['line' => 9],
        'ADDR_CREDITOR_COUNTRY' => ['line' => 10],
        'AMOUNT' => ['line' => 18],
        'CURRENCY' => ['line' => 19],
        'ADDR_DEBITOR_TYPE' => ['line' => 20],
        'ADDR_DEBITOR_NAME' => ['line' => 21],
        'ADDR_DEBITOR_STREET_OR_LINE1' => ['line' => 22],
        'ADDR_DEBITOR_HOUSE_OR_LINE2' => ['line' => 23],
        'ADDR_DEBITOR_NPA' => ['line' => 24],
        'ADDR_DEBITOR_CITY' => ['line' => 25],
        'ADDR_DEBITOR_COUNTRY' => ['line' => 26],
        'REFERENCE_TYPE' => ['line' => 27],
        'REFERENCE' => ['line' => 28],
        'COMMUNICATION' => ['line' => 29],
        'EPD' => ['line' => 30],
        'ADDITIONNAL_INFO' => ['line' => 31],
        'PARAMS1' => ['line' => 32],
        'PARAMS2' => ['line' => 33]
    ]
];

define('MODULUS', 97);

/* thanks to https://commons.apache.org/proper/commons-validator/apidocs/src-html/org/apache/commons/validator/routines/checkdigit/IBANCheckDigit.html */
function iso7064mod97_10 (string $ref): int {
    define('MAX_TOTAL', 999999999);
    define('MAX_ALPHANUMERIC', 35);
    $LETTER_TO_NUMBER = [
        '0' => 0, '1' => 1, '2' => 2, '3' => 3, '4' => 4,
        '5' => 5, '6' => 6, '7' => 7, '8' => 8, '9' => 9,
        'a' => 10, 'b' => 11, 'c' => 12, 'd' => 13, 'e' => 14, 'f' => 15, 
        'g' => 16, 'h' => 17, 'i' => 18, 'j' => 19, 'k' => 20, 'l' => 21,
        'm' => 22, 'n' => 23, 'o' => 24, 'p' => 25, 'q' => 26, 'r' => 27,
        's' => 28, 't' => 29, 'u' => 30, 'v' => 31, 'w' => 32, 'x' => 33,
        'y' => 34, 'z' => 35, 'A' => 10, 'B' => 11, 'C' => 12, 'D' => 13,
        'E' => 14, 'F' => 15, 'G' => 16, 'H' => 17, 'I' => 18, 'J' => 19,
        'K' => 20, 'L' => 21, 'M' => 22, 'N' => 23, 'O' => 24, 'P' => 25,
        'Q' => 26, 'R' => 27, 'S' => 28, 'T' => 29, 'U' => 30, 'V' => 31,
        'W' => 32, 'X' => 33, 'Y' => 34, 'Z' => 35
    ];
    $total = 0;
    for ($i = 0; $i < strlen($ref); $i++) {
        $value = 0;
        if (isset($LETTER_TO_NUMBER[$ref[$i]])) { $value = $LETTER_TO_NUMBER[$ref[$i]]; }
        if ($value < 0 || $value > MAX_ALPHANUMERIC) { return -1; }
        $total = ($value > 9 ? $total * 100 : $total * 10) + $value;
        if ($total > MAX_TOTAL) {
            $total = $total % MODULUS;
        }
    }
    return $total;
}

function swissMod10(string $ref): int {
    $bvr_table = [
        [0, 9, 4, 6, 8, 2, 7, 1, 3, 5],
        [9, 4, 6, 8, 2, 7, 1, 3, 5, 0],
        [4, 6, 8, 2, 7, 1, 3, 5, 0, 9],
        [6, 8, 2, 7, 1, 3, 5, 0, 9, 4],
        [8, 2, 7, 1, 3, 5, 0, 9, 4, 6],
        [2, 7, 1, 3, 5, 0, 9, 4, 6, 8],
        [7, 1, 3, 5, 0, 9, 4, 6, 8, 2],
        [1, 3, 5, 0, 9, 4, 6, 8, 2, 7],
        [3, 5, 0, 9, 4, 6, 8, 2, 7, 1],
        [5, 0, 9, 4, 6, 8, 2, 7, 1, 3]
    ];

    $r = 0;
    //$ref = strrev($ref);
    for ($i = 0; $i < strlen($ref); $i++) {
        $r = $bvr_table[$r][intval($ref[$i])];
    }
    return [0, 9, 8, 7, 6, 5, 4, 3, 2, 1][$r];
}

function get_iso7064_checksum (string $value): string {
    $value = substr($value, 4) . substr($value, 0, 2) . '00';
    return sprintf('%02d', (MODULUS + 1) - iso7064mod97_10($value));
}

function iban_verify (string $iban): bool {
    return iso7064mod97_10(substr($iban, 4) . substr($iban, 0, 4)) % MODULUS === 1;
}

/* creditor reference starts with RF and can be anything */
function creditorref_verify (string $reference): bool {
    return iso7064mod97_10(substr($reference, 4) . substr($reference, 0, 4)) % MODULUS === 1;
}

/* reference is a swiss specific code that part is given by the bank and the
 * the remaining space is free to use by the user. It use a modulo 10 algorithm.
 */
function reference_verify (string $reference): bool {
    if (!ctype_digit($reference)) { return false; }
    $checksum = intval(substr($reference, -1, 1));
    $reference = substr($reference, 0, strlen($reference) - 1);
    return swissMod10($reference) === $checksum;
}

/* verify only version 0200, refactor when another version appear */
function verify_qrdata (array &$qrarray): bool {
    global $SWISS_QRSTD;

    if ($qrarray[0] !== 'SPC') { return false; }
    $std = $SWISS_QRSTD[$qrarray[1]];
    if (!isset($std)) { return false; }

    /* can be only 1, means utf-8 */
    if ($qrarray[$std['CODING']['line']] !== '1') { return false; }

    /* make all upper case for easier comparison */
    $qrarray[$std['REFERENCE_TYPE']['line']] = strtoupper($qrarray[$std['REFERENCE_TYPE']['line']]);
    $qrarray[$std['CURRENCY']['line']] = strtoupper($qrarray[$std['CURRENCY']['line']]);
    $qrarray[$std['ADDR_CREDITOR_COUNTRY']['line']] = strtoupper($qrarray[$std['ADDR_CREDITOR_COUNTRY']['line']]);
    $qrarray[$std['ADDR_DEBITOR_COUNTRY']['line']] = strtoupper($qrarray[$std['ADDR_DEBITOR_COUNTRY']['line']]);

    if ($qrarray[$std['EPD']['line']] !== 'EPD') { echo "EPD FALSE\n"; return false; }

    /* RESERVED FOR FUTURE USE, MUST BE EMPTY */
    foreach($std['_RESERVED'] as $reserved) {
        if (!empty($qrarray[$reserved])) { return false; }
    }

    /* MANDATORY FIELDS */
    if (empty($qrarray[$std['IBAN']['line']])) { return false; }
    if (empty($qrarray[$std['CURRENCY']['line']])) { return false; }
    if (empty($qrarray[$std['ADDR_CREDITOR_TYPE']['line']])) { return false; }
    if (empty($qrarray[$std['ADDR_DEBITOR_TYPE']['line']])) { return false; }
    if (empty($qrarray[$std['ADDR_CREDITOR_NAME']['line']])) { return false; }
    if (empty($qrarray[$std['ADDR_DEBITOR_NAME']['line']])) { return false; }
    if (empty($qrarray[$std['ADDR_CREDITOR_COUNTRY']['line']])) { return false; }
    if (empty($qrarray[$std['ADDR_DEBITOR_COUNTRY']['line']])) { return false; }

    /* verify IBAN checksum */
    if (iban_verify($qrarray[$std['IBAN']['line']]) === false) { echo "IBAN FALSE\n"; return false; }

    switch($qrarray[$std['REFERENCE_TYPE']['line']]) {
        case 'SCOR':
            $len = strlen($qrarray[$std['REFERENCE']['line']]);
            if ($len < 5 || $len > 25) { return false; }
            if (creditorref_verify($qrarray[$std['REFERENCE']['line']]) === false) { return false; }
            break;
        case 'QRR':
            /* For QRR, the bank identifier must start with 3 as per swissqr documentation */
            if (substr($qrarray[$std['IBAN']['line']], 4, 1) !== '3') { return false; }
            /* QRR is only for CHF and EUR */
            if (
                $qrarray[$std['CURRENCY']['line']] !== 'CHF'
                && $qrarray[$std['CURRENCY']['line']] !== 'EUR'
            ) { return false; }
            if (strlen($qrarray[$std['REFERENCE']['line']]) !== 27) { return false; }
            if (reference_verify($qrarray[$std['REFERENCE']['line']]) === false) { return false; }
            break;
        case 'NON': 
            if (!empty($qrarray[$std['REFERENCE']['line']])) { return false; }
            break;
        default: return false;
    }

    foreach(['ADDR_CREDITOR', 'ADDR_DEBITOR'] as $addrs) {
        if (strlen($qrarray[$std[$addrs . '_COUNTRY']['line']]) !== 2) { return false; } 
        switch($qrarray[$std[$addrs . '_TYPE']['line']]) {
            case 'S':
                break;
            case 'K':
                if (!empty($qrarray[$std[$addrs . '_NPA']['line']])) { return false; }
                if (!empty($qrarray[$std[$addrs . '_CITY']['line']])) { return false; }
                if (empty($qrarray[$std[$addrs . '_HOUSE_OR_LINE2']['line']])) { return false; }
                break;
        }
    }

    if (!empty($qrarray[$std['ADDITIONNAL_INFO']['line']]) && strlen($qrarray[$std['ADDITIONNAL_INFO']['line']]) > 140) { return false; }
    if (!empty($qrarray[$std['COMMUNICATION']['line']]) && strlen($qrarray[$std['COMMUNICATION']['line']]) > 140) { return false; }

    return true;
}

function bexio_from_qrdata (array $qrarray): stdClass {
    global $SWISS_QRSTD;

    $std = $SWISS_QRSTD[$qrarray[1]];
    if (!isset($std)) { return false; }

    $object = new stdClass();

    $object->instructed_amount = new stdClass();
    $object->instructed_amount->currency = $qrarray[$std['CURRENCY']['line']];
    $object->instructed_amount->amount = $qrarray[$std['AMOUNT']['line']];

    /* here is the fun, Bexio seems to have not followd the guideline for Swiss
     * qr bills (which is funny for a company that pretend to specialist ont
     * this particular matter). So, because of that, you have to invent a street
     * address for company that generate bills with K address line 1 empty in 
     * order to have Bexio accept the bill ... line 2 must be splitted into two 
     * parts to have Bexio accept the bill.
     */
    $object->recipient = new stdClass();
    $object->recipient->name = $qrarray[$std['ADDR_CREDITOR_NAME']['line']];
    $object->recipient->country_code = $qrarray[$std['ADDR_CREDITOR_COUNTRY']['line']];

    if ($qrarray[$std['ADDR_CREDITOR_TYPE']['line']] === 'K') {
        $object->recipient->street = $qrarray[$std['ADDR_CREDITOR_STREET_OR_LINE1']['line']];
        if (empty($object->recipient->street)) { $object->recipient->street = ' '; }
        $l2parts = explode(' ', $qrarray[$std['ADDR_CREDITOR_HOUSE_OR_LINE2']['line']], 2);
        $object->recipient->zip = trim(array_shift($l2parts));
        $object->recipient->city = trim(array_shift($l2parts));
    } else {
        $object->recipient->house_number = $qrarray[$std['ADDR_CREDITOR_HOUSE_OR_LINE2']['line']];
        $object->recipient->street = $qrarray[$std['ADDR_CREDITOR_STREET_OR_LINE1']['line']];
        $object->recipient->zip = $qrarray[$std['ADDR_CREDITOR_NPA']['line']];
        $object->recipient->city = $qrarray[$std['ADDR_CREDITOR_CITY']['line']];
    }
    $object->iban = $qrarray[$std['IBAN']['line']];

    switch($qrarray[$std['REFERENCE_TYPE']['line']]) {
        case 'QRR':
        case 'SCOR':
            $object->qr_reference_nr = $qrarray[$std['REFERENCE']['line']];
            break;
        case 'NON':
            $object->message = $qrarray[$std['COMMUNICATION']['line']];
            break;
    }

    if (isset($qrarray[$std['ADDITIONNAL_INFO']['line']]) 
        && !empty($qrarray[$std['ADDITIONNAL_INFO']['line']])) {
        $object->additional_information = $qrarray[$std['ADDITIONNAL_INFO']['line']];
    }
    return $object;
}