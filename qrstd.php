<?php
declare(strict_types=1);

namespace BizCuit\SwissQR\QRCH\Header {
    const QRType =             0;
    const Version =            1;
    const Coding =             2;
}

namespace BizCuit\SwissQR\QRCH\CdtrInf {
    const IBAN =               3;
}

namespace BizCuit\SwissQR\QRCH\Cdtr {
    const AdrTp =              4;
    const Name =               5;
    const StrtNmOrAdrLine1 =   6;
    const BldgNbOrAdrLine2 =   7;
    const PstCd =              8;
    const TwnNm =              9;
    const Ctry =              10;
}

namespace BizCuit\SwissQR\QRCH\UltmtCdtr { 
    const AdrTp =             11;
    const Name =              12;
    const StrtNmOrAdrLine1 =  13;
    const BldgNbOrAdrLine2 =  14;
    const PstCd =             15;
    const TwnNm =             16;
    const Ctry =              17;
}

namespace BizCuit\SwissQR\QRCH\CcyAmt {
    const Amt =               18;
    const Ccy =               19;
}

namespace BizCuit\SwissQR\QRCH\UltmtDbtr {
    const AdrTp =             20;
    const Name =              21;
    const StrtNmOrAdrLine1 =  22;
    const BldgNbOrAdrLine2 =  23;
    const PstCd =             24;
    const TwnNm =             25;
    const Ctry =              26;    
}

namespace BizCuit\SwissQR\QRCH\RmtInf {
    const Tp =                27;
    const Ref =               28;

}

namespace BizCuit\SwissQR\QRCH\AddInf {
    const Ustrd =             29;
    const Trailer =           30;
    const StrdBkgInf =        31;
}

namespace BizCuit\SwissQR\QRCH\AltPmtInf {
    const AltPmt1 =           32;
    const AltPmt2 =           33;
}

namespace BizCuit\SwissQR {
    use \stdClass;

    /* QR Standard according to SIX Group, publishing implementation guideline for
    * the Swiss QR Bill. Follows version 2.2. 
    * https://www.six-group.com/dam/download/banking-services/standardization/qr-bill/ig-qr-bill-v2.2-fr.pdf
    */
    const SWISS_QRSTD = [
        '0200' => [
            '_RESERVED' =>                      [11, 12, 13, 14, 15, 16, 17],
            'VERSION' =>                        1,
            'CODING' =>                         2,
            'IBAN' =>                           3,
            'ADDR_CREDITOR_TYPE' =>             4,
            'ADDR_CREDITOR_NAME' =>             5,
            'ADDR_CREDITOR_STREET_OR_LINE1' =>  6,
            'ADDR_CREDITOR_HOUSE_OR_LINE2' =>   7,
            'ADDR_CREDITOR_NPA' =>              8,
            'ADDR_CREDITOR_CITY' =>             9,
            'ADDR_CREDITOR_COUNTRY' =>          10,
            'AMOUNT' =>                         18,
            'CURRENCY' =>                       19,
            'ADDR_DEBITOR_TYPE' =>              20,
            'ADDR_DEBITOR_NAME' =>              21,
            'ADDR_DEBITOR_STREET_OR_LINE1' =>   22,
            'ADDR_DEBITOR_HOUSE_OR_LINE2' =>    23,
            'ADDR_DEBITOR_NPA' =>               24,
            'ADDR_DEBITOR_CITY' =>              25,
            'ADDR_DEBITOR_COUNTRY' =>           26,
            'REFERENCE_TYPE' =>                 27,
            'REFERENCE' =>                      28,
            'COMMUNICATION' =>                  29,
            'EPD' =>                            30,
            'ADDITIONNAL_INFO' =>               31,
            'ALT_PROCEDURE1' =>                 32,
            'ALT_PROCEDURE2' =>                 33
        ]
    ];


    define('ISO7064_MODULUS',   97);
    define('MAX_TOTAL',         999999999);
    define('MAX_ALPHANUMERIC',  35);
    /* thanks to https://commons.apache.org/proper/commons-validator/apidocs/src-html/org/apache/commons/validator/routines/checkdigit/IBANCheckDigit.html */
    function iso7064mod97_10 (string $ref): int {
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
            if (isset($LETTER_TO_NUMBER[$ref[$i]])) { $value = $LETTER_TO_NUMBER[$ref[$i]]; }
            if ($value < 0 || $value > MAX_ALPHANUMERIC) { return -1; }
            $total = ($value > 9 ? $total * 100 : $total * 10) + $value;
            if ($total > MAX_TOTAL) {
                $total = $total % ISO7064_MODULUS;
            }
        }
        return $total % ISO7064_MODULUS;
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
        for ($i = 0; $i < strlen($ref); $i++) {
            $r = $bvr_table[$r][intval($ref[$i])];
        }
        return [0, 9, 8, 7, 6, 5, 4, 3, 2, 1][$r];
    }

    function get_iso7064_checksum (string $value): string {
        $value = substr($value, 4) . substr($value, 0, 2) . '00';
        return sprintf('%02d', (ISO7064_MODULUS + 1) - iso7064mod97_10($value));
    }

    function iban_verify (string $iban): bool {
        return iso7064mod97_10(substr($iban, 4) . substr($iban, 0, 4)) % ISO7064_MODULUS === 1;
    }

    /* creditor reference starts with RF and can be anything */
    function creditorref_verify (string $reference): bool {
        return iso7064mod97_10(substr($reference, 4) . substr($reference, 0, 4)) % ISO7064_MODULUS === 1;
    }

    function generic_iso7064_checksum (string $reference) {
        return sprintf('%02d', (ISO7064_MODULUS + 1) - iso7064mod97_10($reference));
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

    /**
     * Remove lines before beginning of QR data.
     * 
     * @params string[] $qrarray Raw QR data
     * @return string[] QR data starting with SPC
     */
    function trim_qrdata (array $qrarray): array {
        while (!empty($qrarray) && $qrarray[0] !== 'SPC') { array_shift($qrarray); }
        return $qrarray;
    }

    /**
     * Verify QR data according to SIX Group documentation.
     * 
     * Support only version 2.0 as it's the only one documented yet.
     * 
     * @param string[] $qrarray Raw QR data
     * @param string $error If QR data is invalid, contains the field that is invalid
     * @return bool True if QR data is valid, false otherwise
     * 
     */
    define('MAX_NAME_LEN',              70);
    define('MAX_STREET_LINE1_LEN',      70);
    define('MAX_LINE2_LEN',             70);
    define('MAX_HOUSE_LEN',             16);
    define('MAX_NPA_LEN',               16);
    define('MAX_CITY_LEN',              35);
    define('MAX_COMM_LEN',              140);
    define('MAX_ALT_PROCEDURE_LEN',     100);
    define('QRR_IBAN_NUMBER_POS',       4);
    define('QRR_IBAN_DIGIT',            3);
    define('QRR_REFERENCE_LEN',         27);
    define('SCOR_REFERENCE_MIN_LEN',    5);
    define('SCOR_REFERENCE_MAX_LEN',    25);

    function verify_qrdata (array $qrarray, &$error = ''): bool {
        $std = SWISS_QRSTD['0200'];

        if ($qrarray[0] !== 'SPC') { $error = 'SPC'; return false; }
        if(!isset($qrarray[$std['VERSION']])
            || empty($qrarray[$std['VERSION']])
            // according to https://github.com/swico/www.swiss-qr-invoice.org/issues/14
            // any 02.. must be considered as version 0200
            || substr($qrarray[$std['VERSION']], 0, 2) !== '02') { $error = 'VERSION'; return false; }

        /* can be only 1, means utf-8 */
        if ($qrarray[$std['CODING']] !== '1') { $error = 'CODING'; return false; }

        /* make all upper case for easier comparison */
        $qrarray[$std['REFERENCE_TYPE']] = strtoupper($qrarray[$std['REFERENCE_TYPE']]);
        $qrarray[$std['CURRENCY']] = strtoupper($qrarray[$std['CURRENCY']]);
        $qrarray[$std['ADDR_CREDITOR_COUNTRY']] = strtoupper($qrarray[$std['ADDR_CREDITOR_COUNTRY']]);
        $qrarray[$std['ADDR_DEBITOR_COUNTRY']] = strtoupper($qrarray[$std['ADDR_DEBITOR_COUNTRY']]);

        if ($qrarray[$std['EPD']] !== 'EPD') { $error= 'EPD'; return false; }

        /* RESERVED FOR FUTURE USE, MUST BE EMPTY */
        foreach($std['_RESERVED'] as $reserved) {
            if (!empty($qrarray[$reserved])) { $error = '_RESERVED'; return false; }
        }

        /* MANDATORY FIELDS */
        if (empty($qrarray[$std['IBAN']])) { $error = 'IBAN'; return false; }
        if (empty($qrarray[$std['CURRENCY']])) { $error = 'CURRENCY'; return false; }
        if (empty($qrarray[$std['ADDR_CREDITOR_TYPE']])) { $error = 'ADDR_CREDITOR_TYPE'; return false; }
        if (empty($qrarray[$std['ADDR_DEBITOR_TYPE']])) { $error = 'ADDR_DEBITOR_TYPE'; return false; }
        if (empty($qrarray[$std['ADDR_CREDITOR_NAME']])) { $error = 'ADDR_CREDITOR_NAME'; return false; }
        if (empty($qrarray[$std['ADDR_DEBITOR_NAME']])) { $error = 'ADDR_DEBITOR_NAME'; return false; }
        if (empty($qrarray[$std['ADDR_CREDITOR_COUNTRY']])) { $error = 'ADDR_CREDITOR_COUNTRY'; return false; }
        if (empty($qrarray[$std['ADDR_DEBITOR_COUNTRY']])) { $error = 'ADDR_DEBITOR_COUNTRY'; return false; }

        /* verify IBAN checksum */
        if (iban_verify($qrarray[$std['IBAN']]) === false) { $error = 'IBAN'; return false; }

        switch($qrarray[$std['REFERENCE_TYPE']]) {
            case 'SCOR':
                $len = strlen($qrarray[$std['REFERENCE']]);
                if ($len < SCOR_REFERENCE_MIN_LEN
                    || $len > SCOR_REFERENCE_MAX_LEN) { return false; }
                if (creditorref_verify($qrarray[$std['REFERENCE']]) === false) { $error = 'REFERENCE'; return false; }
                break;
            case 'QRR':
                /* For QRR, the bank identifier must start with 3 as per swissqr documentation */
                if (substr($qrarray[$std['IBAN']], QRR_IBAN_NUMBER_POS, 1) !== strval(QRR_IBAN_DIGIT)) { $error = 'IBAN'; return false; }
                /* QRR is only for CHF and EUR */
                if (
                    $qrarray[$std['CURRENCY']] !== 'CHF'
                    && $qrarray[$std['CURRENCY']] !== 'EUR'
                ) { $error = 'CURRENCY'; return false; }
                if (strlen($qrarray[$std['REFERENCE']]) !== QRR_REFERENCE_LEN) { $error = 'REFERENCE'; return false; }
                if (reference_verify($qrarray[$std['REFERENCE']]) === false) { $error = 'REFERENCE'; return false; }
                break;
            case 'NON': 
                if (!empty($qrarray[$std['REFERENCE']])) { $error = 'REFERENCE'; return false; }
                break;
            default:
                $error = 'REFERENCE_TYPE';
                return false;
        }

        foreach(['ADDR_CREDITOR', 'ADDR_DEBITOR'] as $addrs) {
            if (empty($qrarray[$std[$addrs . '_NAME']])
                || strlen($qrarray[$std[$addrs . '_NAME']]) > MAX_NAME_LEN) { $error = $addrs . '_NAME'; return false; }
            if (empty($qrarray[$std[$addrs . '_COUNTRY']]) 
                || strlen($qrarray[$std[$addrs . '_COUNTRY']]) !== 2) { $error = $addrs . '_COUNTRY'; return false; } 
            switch($qrarray[$std[$addrs . '_TYPE']]) {
                case 'S':
                    if (empty($qrarray[$std[$addrs . '_NPA']]) 
                        || strlen($qrarray[$std[$addrs . '_NPA']]) > MAX_NPA_LEN) { $error = $addrs . '_NPA'; return false; }
                    if (empty($qrarray[$std[$addrs . '_CITY']])
                        || strlen($qrarray[$std[$addrs . '_CITY']]) > MAX_CITY_LEN) { $error = $addrs . '_CITY'; return false; }
                    if (empty($qrarray[$std[$addrs . '_HOUSE_OR_LINE2']])
                        || strlen($qrarray[$std[$addrs . '_HOUSE_OR_LINE2']]) > MAX_HOUSE_LEN) { $error = $addrs . '_HOUSE_OR_LINE2'; return false; }
                    if (!empty($qrarray[$std[$addrs . '_STREET_OR_LINE1']]) 
                        && strlen($qrarray[$std[$addrs . '_STREET_OR_LINE1']]) > MAX_STREET_LINE1_LEN) { $error = $addrs . '_STREET_OR_LINE1'; return false; }
                    break;
                case 'K':
                    if (!empty($qrarray[$std[$addrs . '_NPA']])) { $error = $addrs . '_NPA'; return false; }
                    if (!empty($qrarray[$std[$addrs . '_CITY']])) { $error = $addrs . '_CITY'; return false; }
                    if (empty($qrarray[$std[$addrs . '_HOUSE_OR_LINE2']])
                        || strlen($qrarray[$std[$addrs . '_HOUSE_OR_LINE2']]) > MAX_LINE2_LEN) { $error = $addrs . '_HOUSE_OR_LINE2'; return false; }
                    if (!empty($qrarray[$std[$addrs . '_STREET_OR_LINE1']]) 
                        && strlen($qrarray[$std[$addrs . '_STREET_OR_LINE1']]) > MAX_STREET_LINE1_LEN) { $error = $addrs . '_STREET_OR_LINE1'; return false; }
                    break;
                default:
                    $error = $addrs . '_TYPE';
                    return false;
            }
        }

        if (!empty($qrarray[$std['ADDITIONNAL_INFO']]) 
            && strlen($qrarray[$std['ADDITIONNAL_INFO']]) > MAX_COMM_LEN) { $error = 'ADDITIONNAL_INFO'; return false; }
        if (!empty($qrarray[$std['COMMUNICATION']]) 
            && strlen($qrarray[$std['COMMUNICATION']]) > MAX_COMM_LEN) { $error = 'COMMUNICATION'; return false; }

        if (!empty($qrarray[$std['ALT_PROCEDURE1']]) 
            && strlen($qrarray[$std['ALT_PROCEDURE1']]) > MAX_ALT_PROCEDURE_LEN) { $error = 'ALT_PROCEDURE1'; return false; }
        if (!empty($qrarray[$std['ALT_PROCEDURE2']]) 
            && strlen($qrarray[$std['ALT_PROCEDURE2']]) > MAX_ALT_PROCEDURE_LEN) { $error = 'ALT_PROCEDURE2'; return false; }
        return true;
    }

    /**
     * Convert to outgoing payment
     * 
     * Generate basic stucture for outgoing payment, it must be completed with data
     * according to what you are trying to do.
     * There is many ways to create a payment, this is the one that seem most
     * useful.
     * 
     * @see https://docs.bexio.com/#tag/Outgoing-Payment/operation/ApiOutgoingPayment_POST
     * 
     */

    function bexio_from_qrdata (
        array $qrarray, 
        string $billid = null,
        string $country = 'CH'
    ): false|stdClass {
        $std = SWISS_QRSTD[$qrarray[1]];
        if (!isset($std)) { return false; }

        $object = new stdClass();
        if ($billid) { $object->bill_id = $billid; }
        switch ($qrarray[$std['REFERENCE_TYPE']]) {
            case 'QRR':
            case 'SCOR':
                $object->payment_type = 'QR';
                $object->reference_no = $qrarray[$std['REFERENCE']];
                break;
            case 'NON':
                $object->payment_type = "IBAN";
                $object->message = $qrarray[$std['COMMUNICATION']];
                break;
            default: return false;
        }

        $object->currency_code = $qrarray[$std['CURRENCY']];
        $object->amount = $qrarray[$std['AMOUNT']];
        $object->is_salary_payment = false;

        /* creditor info */
        $object->receiver_iban = $qrarray[$std['IBAN']];
        $object->receiver_name = $qrarray[$std['ADDR_CREDITOR_NAME']];
        $object->receiver_country_code = $qrarray[$std['ADDR_CREDITOR_COUNTRY']];

        if ($qrarray[$std['ADDR_CREDITOR_TYPE']] === 'K') {
            $object->receiver_street = $qrarray[$std['ADDR_CREDITOR_STREET_OR_LINE1']];
            if (empty($object->receiver_street)) { $object->receiver_street = '-'; }
            $l2parts = explode(' ', $qrarray[$std['ADDR_CREDITOR_HOUSE_OR_LINE2']], 2);
            $object->receiver_postcode = trim(array_shift($l2parts));
            $object->receiver_city = trim(array_shift($l2parts));
        } else {
            $object->receiver_house_no = $qrarray[$std['ADDR_CREDITOR_HOUSE_OR_LINE2']];
            $object->receiver_street = $qrarray[$std['ADDR_CREDITOR_STREET_OR_LINE1']];
            if (empty($object->receiver_street)) { $object->receiver_street = '-'; }
            $object->receiver_postcode = $qrarray[$std['ADDR_CREDITOR_NPA']];
            $object->receiver_city= $qrarray[$std['ADDR_CREDITOR_CITY']];
        }

        /* NO FEE can by apply only with domestic payment */
        if ($country !== substr($qrarray[$std['IBAN']], 0, 2)) {
            $object->fee_type = 'BREAKDOWN';
        } else {
            $object->fee_type = 'NO_FEE';
        }
        return $object;
    }
}