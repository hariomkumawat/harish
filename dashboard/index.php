<?php
// NO includes at all - pure test
echo "<h1>TEST: " . rand(1000, 9999) . "</h1>";
echo "<h2>TIME: " . date('H:i:s') . "</h2>";
echo "<h2>VALUE: 0.00</h2>";
die();
?>