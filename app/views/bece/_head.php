<?php
/** BECE: Registration / Results tabs (inside the list card); call with $yq set. The year picker sits in the page head (_year.php). */
echo card_tabs(['bece' => ['Registration', url('bece', $yq)], 'bece/results' => ['Results', url('bece/results', $yq)]], current_route(), 2, 'BECE');
