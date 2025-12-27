<?php
$k='LIC-6A301A5D';
$e='iniadalahakunelabsku@gmail.com';
$m='178BFBFF00860F01|WD-WXCZ07808176';
$target='RNP2mrglNVXnG8r/QWwyIuFeQt184QTgVZeeONCEaXg=';

function check($str, $t, $algo='sha256') {
    $h = hash($algo, $str, true);
    $b64 = base64_encode($h);
    if ($b64 === $t) echo "MATCH [$algo]: $str\n";
}

// Coba kombinasi 2 item
$items = ['k'=>$k, 'e'=>$e, 'm'=>$m];
foreach ($items as $l1=>$v1) {
    foreach ($items as $l2=>$v2) {
        if ($l1==$l2) continue;
        check("$v1|$v2", $target);
        check("$v1$v2", $target);
        check("$v1-$v2", $target);
        check("$v1:$v2", $target);
    }
}

// Coba kombinasi 3 item
foreach ($items as $l1=>$v1) {
    foreach ($items as $l2=>$v2) {
        if ($l1==$l2) continue;
        foreach ($items as $l3=>$v3) {
            if ($l3==$l1 || $l3==$l2) continue;
            check("$v1|$v2|$v3", $target);
            check("$v1$v2$v3", $target);
        }
    }
}

// Coba dengan huruf besar semua / kecil semua
$K = strtoupper($k); $E = strtoupper($e); $M = strtoupper($m);
check("$K|$M|$E", $target);
check("$K|$E|$M", $target);

$k_ = strtolower($k); $e_ = strtolower($e); $m_ = strtolower($m);
check("$k_|$m_|$e_", $target);
check("$k_|$e_|$m_", $target);

// Coba HMAC?
// Jika aplikasi pakai HMAC, kita butuh secret key. Tapi biasanya aplikasi client-side tidak simpan secret key (rawan di-reverse engineer).
// Jadi kemungkinan besar hash biasa.

// Coba algoritma lain?
// RNP2... decoded length is 32 bytes.
// Valid algos producing 32 bytes: sha256, sha3-256, gost, ripemd256, snefru256.
// Paling umum sha256.

echo "Done.\n";
