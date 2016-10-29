<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

/**
 * Description of test
 *
 * @author lijingrun
 */
class TestApp extends BackendApp {

    function index() {
        $dn = array("countryName" => 'XX', "stateOrProvinceName" => 'State', "localityName" => 'SomewhereCity', "organizationName" => 'MySelf', "organizationalUnitName" => 'Whatever', "commonName" => 'mySelf', "emailAddress" => 'user@domain.com');
        $privkeypass = '1234';
        $numberofdays = 365;

        $privkey = openssl_pkey_new();
        $csr = openssl_csr_new($dn, $privkey);
        $sscert = openssl_csr_sign($csr, null, $privkey, $numberofdays);
        openssl_x509_export($sscert, $publickey);
        openssl_pkey_export($privkey, $privatekey, $privkeypass);
        openssl_csr_export($csr, $csrStr);

        echo $privatekey; // Will hold the exported PriKey
        echo $publickey;  // Will hold the exported PubKey
        echo $csrStr;     // Will hold the exported Certificate
        exit;
    }

    //put your code here
}
