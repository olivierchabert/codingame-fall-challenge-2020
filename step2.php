<?php

function getActions()
{
    // $actionCount: the number of spells and recipes in play
    fscanf(STDIN, "%d", $actionCount);
    $actions = ['BREW', 'CAST'];
    for ($i = 0; $i < $actionCount; $i++) {
        // $actionId: the unique ID of this spell or recipe
        // $actionType: in the first league: BREW; later: CAST, OPPONENT_CAST, LEARN, BREW
        // $delta0: tier-0 ingredient change
        // $delta1: tier-1 ingredient change
        // $delta2: tier-2 ingredient change
        // $delta3: tier-3 ingredient change
        // $price: the price in rupees if this is a potion
        // $tomeIndex: in the first two leagues: always 0; later: the index in the tome if this is a tome spell, equal to the read-ahead tax
        // $taxCount: in the first two leagues: always 0; later: the amount of taxed tier-0 ingredients you gain from learning this spell
        // $castable: in the first league: always 0; later: 1 if this is a castable player spell
        // $repeatable: for the first two leagues: always 0; later: 1 if this is a repeatable player spell
        fscanf(STDIN, "%d %s %d %d %d %d %d %d %d %d %d", $actionId, $actionType, $delta0, $delta1, $delta2, $delta3, $price, $tomeIndex, $taxCount, $castable, $repeatable);
        $actions[$actionType][$actionId] = [$delta0, $delta1, $delta2, $delta3, $price, $tomeIndex, $taxCount, $castable, $repeatable];
    }

    uasort(
        $actions['BREW'],
        function ($a, $b) {
            return $b[4] <=> $a[4];
        }
    );

    return $actions;
}

function getInventories()
{
    $inv = [];
    for ($i = 0; $i < 2; $i++) {
        // $inv0: tier-0 ingredients in inventory
        // $score: amount of rupees
        fscanf(STDIN, "%d %d %d %d %d", $inv0, $inv1, $inv2, $inv3, $score);
        $left = 10 - ($inv0 + $inv1 + $inv2 + $inv3);
        $inv[$i] = [$inv0, $inv1, $inv2, $inv3, $score, $left];
    }
    return $inv;
}

function getBestPossibleOrder($orders, $inventory, $withPrice = false)
{
    foreach ($orders as $id => $order) {
        $possible = true;
        for ($i = 0; $i < 4; $i++) {
            if ($inventory[$i] + $order[$i] < 0) {
                $possible = false;
                break;
            }
        }
        if ($possible) {
            return $withPrice ? ['id' => $id, 'price' => $order[4]] : $id;
        }
    }

    return false;
}

function getPossibleCasts($casts, $inventory) {
    $possibleCasts = [];
    foreach ($casts as $id => $cast) {
        if ($cast[7] > 0) {
            $possible = true;
            $space = 0;
            for ($i = 0; $i < 4; $i++) {
                if ($inventory[$i] + $cast[$i] < 0) {
                    $possible = false;
                    break;
                }
                $space += $cast[$i];
            }
            if ($possible && $space <= $inventory[5]) {
                $possibleCasts[$id] = $cast;
            }
        }
    }

    return $possibleCasts;
}

function applyCastOnInventory($cast, $inventory) {
    for ($i = 0; $i < 2; $i++) {
        $inventory[$i] += $cast[$i];
    }
    return $inventory;
}

function getInventoryMissing($order, $inventory) {
    $missing = [];
    for ($i = 0; $i < 2; $i++) {
        $missing[$i] = ($inventory[$i] + $order[$i] < 0) ? $inventory[$i] + $order[$i] : 0;
    }
    return $missing;
}

function getBestCast($casts, $order, $inventory) {
    $missing = getInventoryMissing($order, $inventory);
    $casts = getPossibleCasts($casts, $inventory);
    $missings = [];
    foreach ($casts as $castId => $cast) {
        $missings[$castId] = 0;
        for ($i = 0; $i < 2; $i++) {
            $missings[$castId] += ($missing[$i] + $cast[$i] < 1) ? $missing[$i] + $cast[$i] : 0;
        }
    }
    arsort($missings);
    return array_key_first($missings);
}

// game loop
while (true) {
    $actions = getActions();
    $inventories = getInventories();

    $id = getBestPossibleOrder($actions['BREW'], $inventories[0]);
    if ($id !== false) {
        echo "BREW $id\n";
        continue;
    }

    $id = getBestCast($actions['CAST'], reset($actions['BREW']), $inventories[0]);
    if ($id !== null) {
        echo "CAST $id\n";
        continue;
    }

    echo "REST\n";

//    echo "WAIT\n";
}