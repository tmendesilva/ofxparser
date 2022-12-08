<?php

namespace OfxParser\Entities;

class Transaction extends AbstractEntity
{
    private static $types = [
        'CREDIT' => 'Generic credit',
        'DEBIT' => 'Generic debit',
        'INT' => 'Interest earned or paid',
        'DIV' => 'Dividend',
        'FEE' => 'FI fee',
        'SRVCHG' => 'Service charge',
        'DEP' => 'Deposit',
        'ATM' => 'ATM debit or credit',
        'POS' => 'Point of sale debit or credit',
        'XFER' => 'Transfer',
        'CHECK' => 'Cheque',
        'PAYMENT' => 'Electronic payment',
        'CASH' => 'Cash withdrawal',
        'DIRECTDEP' => 'Direct deposit',
        'DIRECTDEBIT' => 'Merchant initiated debit',
        'REPEATPMT' => 'Repeating payment/standing order',
        'OTHER' => 'Other',
    ];

    /**
     * <TRNTYPE>
     * @var string
     */
    public $type;

    /**
     * Date the transaction was posted <DTPOSTED>
     * @var \DateTimeInterface
     */
    public $date;

    /**
     * Date the user initiated the transaction, if known <DTUSER>
     * @var \DateTimeInterface|null
     */
    public $userInitiatedDate;

    /**
     * <TRNAMT>
     * @var float
     */
    public $amount;

    /**
     * <FITID>
     * @var string
     */
    public $uniqueId;

    /**
     * <NAME>
     * @var string
     */
    public $name;

    /**
     * <MEMO>
     * @var string
     */
    public $memo;

    /**
     * <SIC>
     * @var string
     */
    public $sic;

    /**
     * <CHECKNUM>
     * @var string
     */
    public $checkNumber;

    /**
     * <REFNUM>
     * @var string
     */
    public $refNumber;

    /**
     * Extended name or description <EXTDNAME>
     * @var string
     */
    public $nameExtended;

    /**
     * Payee Id <PAYEEID>
     * @var string
     */
    Public $payeeId;

    /**
     * Payee requisites <PAYEE>
     * @var 
     */
    public $payee;

    /**
     * Bank account of counterparty
     * @var 
     */
    public $bankAccountTo;

    /**
     * Credit card account of counterparty
     * @var 
     */
    public $cardAccountTo;

    /**
     * Get the associated type description
     *
     * @return string
     */
    public function typeDesc()
    {
        // Cast SimpleXMLObject to string
        $type = (string)$this->type;
        return array_key_exists($type, self::$types) ? self::$types[$type] : '';
    }
}
