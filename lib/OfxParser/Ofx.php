<?php

namespace OfxParser;

use SimpleXMLElement;
use OfxParser\Utils;
use OfxParser\Entities\AccountInfo;
use OfxParser\Entities\BankAccount;
use OfxParser\Entities\Institute;
use OfxParser\Entities\SignOn;
use OfxParser\Entities\Statement;
use OfxParser\Entities\Status;
use OfxParser\Entities\Transaction;
use OfxParser\Entities\Payee;

/**
 * The OFX object
 *
 * Heavily refactored from Guillaume Bailleul's grimfor/ofxparser
 *
 * Second refactor by Oliver Lowe to unify the API across all
 * OFX data-types.
 *
 * Based on Andrew A Smith's Ruby ofx-parser
 *
 * @author Guillaume BAILLEUL <contact@guillaume-bailleul.fr>
 * @author James Titcumb <hello@jamestitcumb.com>
 * @author Oliver Lowe <mrtriangle@gmail.com>
 */
class Ofx
{
    /**
     * @var Header[]
     */
    public $header = [];

    /**
     * @var SignOn
     */
    public $signOn;

    /**
     * @var AccountInfo[]
     */
    public $signupAccountInfo;

    /**
     * @var BankAccount[]
     */
    public $bankAccounts = [];

    /**
     * Only populated if there is only one bank account
     * @var BankAccount|null
     * @deprecated This will be removed in future versions
     */
    public $bankAccount;

    /**
     * @param SimpleXMLElement $xml
     * @throws \Exception
     */
    public function __construct(SimpleXMLElement $xml)
    {
        $this->signOn = $this->buildSignOn($xml->SIGNONMSGSRSV1->SONRS);
        $this->signupAccountInfo = $this->buildAccountInfo($xml->SIGNUPMSGSRSV1->ACCTINFOTRNRS);

        if (isset($xml->BANKMSGSRSV1)) {
            $this->bankAccounts = $this->buildBankAccounts($xml);
        } elseif (isset($xml->CREDITCARDMSGSRSV1)) {
            $this->bankAccounts = $this->buildCreditAccounts($xml);
        }

        // Set a helper if only one bank account
        if (count($this->bankAccounts) === 1) {
            $this->bankAccount = $this->bankAccounts[0];
        }
    }

    /**
     * Get the transactions that have been processed
     *
     * @return array
     * @deprecated This will be removed in future versions
     */
    public function getTransactions()
    {
        return $this->bankAccount->statement->transactions;
    }

    /**
     * @param array $header
     * @return Ofx
     */
    public function buildHeader(array $header)
    {
        $this->header = $header;

        return $this;
    }

    /**
     * @param SimpleXMLElement $xml
     * @return SignOn
     * @throws \Exception
     */
    protected function buildSignOn(SimpleXMLElement $xml)
    {
        $signOn = new SignOn();
        $signOn->status = $this->buildStatus($xml->STATUS);
        $signOn->date = Utils::createDateTimeFromStr($xml->DTSERVER, true);
        $signOn->language = $xml->LANGUAGE;

        $signOn->institute = new Institute();
        $signOn->institute->name = $xml->FI->ORG;
        $signOn->institute->id = $xml->FI->FID;

        return $signOn;
    }

    /**
     * @param SimpleXMLElement|null $xml
     * @return array AccountInfo
     */
    private function buildAccountInfo(SimpleXMLElement $xml = null)
    {
        if (null === $xml || !isset($xml->ACCTINFO)) {
            return [];
        }

        $accounts = [];
        foreach ($xml->ACCTINFO as $account) {
            $accountInfo = new AccountInfo();
            $accountInfo->desc = $account->DESC;
            $accountInfo->number = $account->ACCTID;
            $accounts[] = $accountInfo;
        }

        return $accounts;
    }

    /**
     * @param SimpleXMLElement $xml
     * @return array
     * @throws \Exception
     */
    private function buildCreditAccounts(SimpleXMLElement $xml)
    {
        // Loop through the bank accounts
        $bankAccounts = [];

        foreach ($xml->CREDITCARDMSGSRSV1->CCSTMTTRNRS as $accountStatement) {
            $bankAccounts[] = $this->buildCreditAccount($accountStatement);
        }
        return $bankAccounts;
    }

    /**
     * @param SimpleXMLElement $xml
     * @return array
     * @throws \Exception
     */
    private function buildBankAccounts(SimpleXMLElement $xml)
    {
        // Loop through the bank accounts
        $bankAccounts = [];
        foreach ($xml->BANKMSGSRSV1->STMTTRNRS as $accountStatement) {
            foreach ($accountStatement->STMTRS as $statementResponse) {
                $bankAccounts[] = $this->buildBankAccount($accountStatement->TRNUID, $statementResponse);
            }
        }
        return $bankAccounts;
    }

    /**
     * @param string $transactionUid
     * @param SimpleXMLElement $statementResponse
     * @return BankAccount
     * @throws \Exception
     */
    private function buildBankAccount($transactionUid, SimpleXMLElement $statementResponse)
    {
        $bankAccount = new BankAccount();
        $bankAccount->transactionUid = $transactionUid;
        $bankAccount->agencyNumber = $statementResponse->BANKACCTFROM->BRANCHID;
        $bankAccount->accountNumber = $statementResponse->BANKACCTFROM->ACCTID;
        $bankAccount->routingNumber = $statementResponse->BANKACCTFROM->BANKID;
        $bankAccount->accountType = $statementResponse->BANKACCTFROM->ACCTTYPE;
        $bankAccount->balance = $statementResponse->LEDGERBAL->BALAMT;
        $bankAccount->balanceDate = Utils::createDateTimeFromStr(
            $statementResponse->LEDGERBAL->DTASOF,
            true
        );

        $bankAccount->statement = new Statement();
        $bankAccount->statement->currency = $statementResponse->CURDEF;

        $bankAccount->statement->startDate = Utils::createDateTimeFromStr(
            $statementResponse->BANKTRANLIST->DTSTART
        );

        $bankAccount->statement->endDate = Utils::createDateTimeFromStr(
            $statementResponse->BANKTRANLIST->DTEND
        );

        $bankAccount->statement->transactions = $this->buildTransactions(
            $statementResponse->BANKTRANLIST->STMTTRN
        );

        return $bankAccount;
    }

    /**
     * @param SimpleXMLElement $xml
     * @return BankAccount
     * @throws \Exception
     */
    private function buildCreditAccount(SimpleXMLElement $xml)
    {
        $nodeName = 'CCACCTFROM';
        if (!isset($xml->CCSTMTRS->$nodeName)) {
            $nodeName = 'BANKACCTFROM';
        }

        $creditAccount = new BankAccount();
        $creditAccount->transactionUid = $xml->TRNUID;
        $creditAccount->agencyNumber = $xml->CCSTMTRS->$nodeName->BRANCHID;
        $creditAccount->accountNumber = $xml->CCSTMTRS->$nodeName->ACCTID;
        $creditAccount->routingNumber = $xml->CCSTMTRS->$nodeName->BANKID;
        $creditAccount->accountType = $xml->CCSTMTRS->$nodeName->ACCTTYPE;
        $creditAccount->balance = $xml->CCSTMTRS->LEDGERBAL->BALAMT;
        $creditAccount->balanceDate = Utils::createDateTimeFromStr($xml->CCSTMTRS->LEDGERBAL->DTASOF, true);

        $creditAccount->statement = new Statement();
        $creditAccount->statement->currency = $xml->CCSTMTRS->CURDEF;
        $creditAccount->statement->startDate = Utils::createDateTimeFromStr($xml->CCSTMTRS->BANKTRANLIST->DTSTART);
        $creditAccount->statement->endDate = Utils::createDateTimeFromStr($xml->CCSTMTRS->BANKTRANLIST->DTEND);
        $creditAccount->statement->transactions = $this->buildTransactions($xml->CCSTMTRS->BANKTRANLIST->STMTTRN);

        return $creditAccount;
    }

    /**
     * @param SimpleXMLElement $transactions
     * @return array
     * @throws \Exception
     */
    private function buildTransactions(SimpleXMLElement $transactions)
    {
        $return = [];
        foreach ($transactions as $t) {
            $transaction = new Transaction();
            $transaction->type = (string) $t->TRNTYPE;
            $transaction->date = Utils::createDateTimeFromStr($t->DTPOSTED);
            if ('' !== (string) $t->DTUSER) {
                $transaction->userInitiatedDate = Utils::createDateTimeFromStr($t->DTUSER);
            }
            $transaction->amount = Utils::createAmountFromStr($t->TRNAMT);
            $transaction->uniqueId = (string) $t->FITID;
            $transaction->name = (string) $t->NAME;
            $transaction->memo = (string) $t->MEMO;
            $transaction->sic = (string) $t->SIC;
            // CHECKNUM
            $transaction->checkNumber = (string) $t->CHECKNUM;
            // REFNUM
            $transaction->refNumber = (string) $t->REFNUM;
            // EXTDNAME
            $transaction->nameExtended = (string) $t->EXTDNAME;
            // PAYEEID
            $transaction->payeeId = (string) $t->PAYEEID;
            // PAYEE
            if(isset($t->PAYEE)) $transaction->payee = $this->buildPayee($t->PAYEE);
            // BANKACCTTO
            if(isset($t->BANKACCTTO)) $transaction->bankAccountTo = $this->buildBankAccountTo($t->BANKACCTTO);
            // CCACCTTO
            if(isset($t->CCACCTTO)) $transaction->cardAccountTo = $this->buildCardAccountTo($t->CCACCTTO);

            $return[] = $transaction;
        }

        return $return;
    }

    /**
     * @param SimpleXMLElement $xml
     * @return Status
     */
    private function buildStatus(SimpleXMLElement $xml)
    {
        $status = new Status();
        $status->code = $xml->CODE;
        $status->severity = $xml->SEVERITY;
        $status->message = $xml->MESSAGE;

        return $status;
    }

    /**
     * Builds payee of transaction
     * @param SimpleXMLElement $xml
     * @return Payee
     */
    private function buildPayee(SimpleXMLElement $xml)
    {
        $payee = new Payee();
        // name
        $payee->name = (string) $xml->NAME;
        // address
        $address = [];
        if((string) $xml->ADDR1) $address[] = (string) $xml->ADDR1;
        if((string) $xml->ADDR2) $address[] = (string) $xml->ADDR2;
        if((string) $xml->ADDR3) $address[] = (string) $xml->ADDR3;
        if(count($address) > 0) $payee->address = $address;

        $payee->city = (string) $xml->CITY;
        $payee->state = (string) $xml->STATE;
        $payee->postalCode = (string) $xml->POSTALCODE;
        $payee->country = (string) $xml->COUNTRY;
        $payee->phone = (string) $xml->PHONE;

        return $payee;
    }

    /**
     * Builds corresponding bank account of transaction
     * @param SimpleXMLElement $xml
     * @return BankAccount
     */
    public function buildBankAccountTo(SimpleXMLElement $xml)
    {
        $bankAccountTo = new BankAccount();
        $bankAccountTo->routingNumber = (string) $xml->BANKID;
        $bankAccountTo->agencyNumber = (string) $xml->BRANCHID;
        $bankAccountTo->accountNumber = (string) $xml->ACCTID;
        $bankAccountTo->accountType = (string) $xml->ACCTTYPE;

        // remove other attrs
        unset($bankAccountTo->balance, $bankAccountTo->balanceDate, $bankAccountTo->statement, $bankAccountTo->transactionUid);

        return $bankAccountTo;
    }

    /**
     * Builds corresponding credit card account of transaction
     * @param SimpleXMLElement $xml
     * @return BankAccount
     */
    public function buildCardAccountTo(SimpleXMLElement $xml)
    {
        $cardAccountTo = new BankAccount();
        $cardAccountTo->accountNumber = (string) $xml->ACCTID;

        // remove other attrs
        unset($cardAccountTo->routingNumber, $cardAccountTo->agencyNumber, $cardAccountTo->accountType, $cardAccountTo->balance, $cardAccountTo->balanceDate, $cardAccountTo->statement, $cardAccountTo->transactionUid);

        return $cardAccountTo;
    }
}
