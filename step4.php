<?php

namespace Step3;

class Recipe
{
    /** @var int[] */
    public $delta;

    public function __construct(int $a = 0, int $b = 0, int $c = 0, int $d = 0)
    {
        $this->delta = ['a' => $a, 'b' => $b, 'c' => $c, 'd' => $d];
    }

    public function sum(): int
    {
        return array_sum($this->delta);
    }

    public function exceedLimit(Recipe $limit): bool
    {
        foreach ($this->delta as $ingrendient => $qty) {
            if ($qty > $limit->delta[$ingrendient]) {
                return true;
            }
        }

        return false;
    }

    public function max(Recipe $recipe): Recipe
    {
        return new Recipe(
            -max($this->delta['a'], $recipe->delta['a']),
            -max($this->delta['b'], $recipe->delta['b']),
            -max($this->delta['c'], $recipe->delta['c']),
            -max($this->delta['d'], $recipe->delta['d'])
        );
    }

    public function add(Recipe $recipe, int $repeat = 1): Recipe
    {
        foreach ($this->delta as $ingredient => $qty) {
            $this->delta[$ingredient] += $recipe->delta[$ingredient] * $repeat;
        }
        return $this;
    }

    public function cost(): int
    {
        $cost = 0;
        foreach ($this->delta as $qty) {
            if ($qty < 0) {
                $cost += $qty;
            }
        }

        return $cost;
    }

    public function gain(): int
    {
        $gain = 0;
        foreach ($this->delta as $qty) {
            if ($qty > 0) {
                $gain += $qty;
            }
        }

        return $gain;
    }

    public function __toString(): string
    {
        return '['.http_build_query($this->delta,'',', ').']';
    }
}

class Inventory extends Recipe
{
    public function canProvide(Recipe $need, int $repeat = 1): bool
    {
        foreach ($this->delta as $ingredient => $qty) {
            if ($qty + ($need->delta[$ingredient] * $repeat) < 0) {
                return false;
            }
        }

        return true;
    }

    public function ingredients()
    {
        asort($this->delta);

        return array_keys($this->delta);
    }

    public function isFull(): bool
    {
        return $this->sum() == 10;
    }

    public function left(): int
    {
        return 10 - $this->sum();
    }

    public function __toString(): string
    {
        return 'Inventory: '.parent::__toString();
    }
}

abstract class AbstractSpell
{
    /** @var Recipe */
    public $recipe;
    /** @var int */
    public $id;

    public function __construct(int $id, Recipe $recipe)
    {
        $this->id = $id;
        $this->recipe = $recipe;
    }
}

class Order extends AbstractSpell
{
    /** @var int */
    public $rupees;
    /** @var int */
    public $bonus;

    public function __construct(int $id, Recipe $recipe, int $rupees, int $bonus)
    {
        parent::__construct($id, $recipe);
        $this->rupees = $rupees;
        $this->bonus = $bonus;
    }

    public function __toString(): string
    {
        return 'Order n°'.$this->id.': ('.$this->rupees.') '.$this->recipe;
    }
}

class Spell extends AbstractSpell
{
    /** @var bool */
    public $active;
    /** @var bool */
    public $repeatable;
    /** @var Player */
    public $owner;
    /** @var int */
    public $nbRepeat = 1;

    public function __construct(int $id, Recipe $recipe, Player $owner, bool $active, bool $repeatable)
    {
        parent::__construct($id, $recipe);
        $this->owner = $owner;
        $this->active = $active;
        $this->repeatable = $repeatable;

        $this->owner->spells[] = $this;
    }

    public function isCastable(int $repeat = 1, $onlyActive = true): bool
    {
        $active = $onlyActive ? $this->active : !$this->active;

        return $active &&
            $this->owner->inventory->left() > $this->recipe->sum() &&
            $this->owner->inventory->canProvide($this->recipe, $repeat);
    }

    public function __toString(): string
    {
        return 'Spell n°'.$this->id.': ('.
            ($this->active?'en':'dis').'able, '.
            ($this->repeatable?'repeatable':'').', '.
            'x'.($this->nbRepeat).') '.
            $this->recipe;
    }
}

class Player
{
    /** @var Inventory */
    public $inventory;
    /** @var Spell[] */
    public $spells = [];
    /** @var int */
    public $rupees;

    /**
     * @param bool $onlyActive
     * @return Spell[]
     */
    public function castables($onlyActive = true): array
    {
        return array_filter(
            $this->spells,
            function (Spell $spell) use ($onlyActive) {
                return $spell->isCastable(1, $onlyActive);
            }
        );
    }

    /**
     * @param Inventory $inventory
     * @param Commerce $commerce
     * @param bool $onlyActive
     * @return Spell[]
     */
    public function bestSpell(Inventory $inventory, Commerce $commerce, $onlyActive = true): array
    {
        $castables = $this->castables($onlyActive);
        $castablesByCost = [];
        $max = $commerce->max()->add(new Recipe(1, 1, 1, 1));

        foreach ($commerce->orders as $i => $order) {
            foreach ($castables as $spell) {
                $repeat = 0;
                $cost = 0;
                $rupees = 0;
                $isCastable = false;
                $nbRepeat = 1;
                do {
                    $repeat++;
                    $new = (clone $inventory)
                        ->add($order->recipe)
                        ->add($spell->recipe, $repeat);
                    if (!$new->exceedLimit($max)) {
                        $cost = $new->cost();
                        $rupees = $order->rupees;
                        $isCastable = true;
                        $nbRepeat = $repeat;
                    }
                } while ($spell->repeatable && $spell->isCastable($repeat + 1));

                if ($isCastable) {
                    $spell->nbRepeat = $nbRepeat;
                    $castablesByCost[$cost][$rupees] = $spell;
                }
            }
        }

        $bests = [];
        if (!empty($castablesByCost)) {
            krsort($castablesByCost);
            $bests = reset($castablesByCost);
            krsort($bests);
        }

        return $bests;
    }

    public function toRest()
    {
        $toRest = [];
        foreach ($this->spells as $spell) {
            if (!$spell->active) {
                $toRest[] = $spell;
            }
        }

        return $toRest;
    }

    public function get(int $id): ?Spell
    {
        foreach ($this->spells as $spell) {
            if ($spell->id === $id) {
                return $spell;
            }
        }
        return null;
    }

    public function rest()
    {
        foreach ($this->spells as $spell) {
            $spell->active = true;
            $spell->nbRepeat = 1;
        }
    }
}

class Commerce
{
    /** @var Order[] */
    public $orders = [];

    public function sort()
    {
        uasort(
            $this->orders,
            function (Order $a, Order $b) {
                return $b->rupees <=> $a->rupees;
            }
        );
    }

    public function best(Inventory $inventory): ?Order
    {
        foreach ($this->orders as $order) {
            if ($inventory->canProvide($order->recipe)) {
                return $order;
            }
        }

        return null;
    }

    public function get(int $id): ?Order
    {
        foreach ($this->orders as $order) {
            if ($order->id === $id) {
                return $order;
            }
        }
        return null;
    }

    public function remove(int $id)
    {
        $this->orders = array_filter($this->orders, function (Order $order) use ($id) {
            return $order->id != $id;
        });
    }

    public function max(): Recipe
    {
        $recipe = new Recipe();
        foreach ($this->orders as $order) {
            $recipe = $recipe->max($order->recipe);
        }

        return $recipe;
    }
}

class TomeSpell extends AbstractSpell
{
    /** @var int */
    public $index;
    /** @var int */
    public $tax;
    /** @var bool */
    public $repeatable;

    public function __construct(int $id, Recipe $recipe, int $index, int $tax, int $repeatable)
    {
        parent::__construct($id, $recipe);
        $this->index = $index;
        $this->tax = $tax;
        $this->repeatable = $repeatable;
    }

    public function __toString(): string
    {
        return 'TomeSpell n°'.$this->id.': ('.
            ($this->repeatable?'repeatable':'').', '.
            ($this->tax).', '.
            ($this->index).', '.
            $this->recipe;
    }
}

class Tome
{
    /** @var TomeSpell[] */
    public $spells = [];

    /**
     * @param Inventory $inventory
     * @return TomeSpell[]
     */
    public function learnables(Inventory $inventory): array
    {
        $learnables = [];
        foreach ($this->spells as $tomeSpell) {
            if ($inventory->canProvide(new Recipe(-$tomeSpell->index))) {
                $learnables[] = $tomeSpell;
            }
        }

        return $learnables;
    }

    /**
     * @param Player $player
     * @return TomeSpell[]
     */
    public function free(Player $player): array
    {
        $learnables = [];
        foreach ($this->spells as $tomeSpell) {
            $cost = $tomeSpell->recipe->cost();
            if ($cost === 0) {
                if ($player->inventory->canProvide(new Recipe(-$tomeSpell->index))) {
                    $learnables[] = $tomeSpell;
                }
            }
        }

        if (!empty($learnables)) {
            $ingredients = new Recipe();
            krsort($ingredients->delta);
            foreach ($player->spells as $spell)
            {
                if ($spell->recipe->cost() == 0) {
                    foreach ($spell->recipe->delta as $ingredient => $qty) {
                        $ingredients->delta[$ingredient] += $qty;
                    }
                }
            }
            asort($ingredients->delta);
            $ingredients = array_keys($ingredients->delta);
            uasort(
                $learnables,
                function (TomeSpell $a, TomeSpell $b) use ($ingredients) {
                    foreach ($ingredients as $ingredient) {
                        if ($b->recipe->delta[$ingredient] != $a->recipe->delta[$ingredient]) {
                            return $b->recipe->delta[$ingredient] <=> $a->recipe->delta[$ingredient];
                        }
                    } // TODO faire des proportions
                    return 0;
                }
            );
        }

        return $learnables;
    }

    /**
     * @param Inventory $inventory
     * @return TomeSpell[]
     */
    public function best(Inventory $inventory): array
    {
        $learnables = $this->learnables($inventory);

        if (!empty($learnables)) {
            $ingredients = $inventory->ingredients();
            uasort(
                $learnables,
                function (TomeSpell $a, TomeSpell $b) use ($ingredients) {
                    $cost = $b->recipe->cost() <=> $a->recipe->cost();
                    if ($cost === 0) {
                        foreach ($ingredients as $ingredient) {
                            if ($a->recipe->delta[$ingredient] != $b->recipe->delta[$ingredient]) {
                                return $a->recipe->delta[$ingredient] <=> $b->recipe->delta[$ingredient];
                            }
                        }
                    }

                    return $cost;
                }
            );
        }

        return $learnables;
    }

    public function get(int $id): ?TomeSpell
    {
        foreach ($this->spells as $spell) {
            if ($spell->id === $id) {
                return $spell;
            }
        }
        return null;
    }

    public function remove(int $id)
    {
        $this->spells = array_filter($this->spells, function (TomeSpell $spell) use ($id) {
            return $spell->id != $id;
        });
    }

    public function __toString(): string
    {
        $msg = 'Tome:'."\n";
        foreach ($this->spells as $spell) {
            $msg .= "\t".$spell."\n";
        }

        return $msg;
    }
}

class Action
{
    const TYPE_BREW = 'BREW';
    const TYPE_CAST = 'CAST';
    const TYPE_LEARN = 'LEARN';
    const TYPE_REST = 'REST';
    const TYPE_WAIT = 'WAIT';

    /** @var string */
    public $type;
    /** @var AbstractSpell */
    public $spell;
    /** @var int */
    public $iteration = 0;
    /** @var int */
    public $rupees = 0;
    /** @var Step */
    public $step;

    public function __construct(string $type, Step $step, ?AbstractSpell $spell = null)
    {
        $this->type = $type;
        $this->spell = $spell;
        $this->step = $this->next($step);
        $this->process();
    }

    public function __toString(): string
    {
        $action = $this->type;
        switch ($this->type) {
            case self::TYPE_BREW:
            case self::TYPE_LEARN:
                $action .= ' '.$this->spell->id;
                break;
            case self::TYPE_CAST:
                $action .= ' '.$this->spell->id.' '.$this->spell->nbRepeat;
                break;
        }

        return $action;
    }

    public function next(Step $current): Step
    {
        /** @var Step $next */
        $next = unserialize(serialize($current)); // make deep clone
        $next->iteration++;

        switch ($this->type) {
            case self::TYPE_BREW:
                $order = $next->commerce->get($this->spell->id);
                $next->players[0]->rupees += $order->rupees;
                $next->commerce->remove($this->spell->id);
                break;
            case self::TYPE_CAST:
                $spell = $next->players[0]->get($this->spell->id);
                for ($i = $spell->nbRepeat; $i > 0; $i--) {
                    $next->players[0]->inventory->add($spell->recipe);
                }
                $spell->active = false;
                $spell->nbRepeat = 1;
                break;
            case self::TYPE_LEARN:
                $spell = $next->tome->get($this->spell->id);
                new Spell($this->spell->id, $spell->recipe, $next->players[0], true, $spell->repeatable);
                $next->tome->remove($this->spell->id);
                break;
            case self::TYPE_REST:
                $next->players[0]->rest();
                break;
        }

        return $next;
    }

    public function process(string $type, int $max)
    {
        $action = $this;
        while ($action->step->iteration <= $max && !in_array($action->type, [self::TYPE_BREW, self::TYPE_WAIT])) {
            $action = $action->step->bestAction($type);
        }

        $this->iteration = $action->step->iteration;
        $this->rupees = ($action->type === self::TYPE_BREW) ? $action->spell->rupees : 0;
    }
}

class Step
{
    /** @var Player[] */
    public $players = [];
    /** @var Commerce */
    public $commerce;
    /** @var Tome */
    public $tome;
    /** @var int */
    public $iteration = 0;

    public function __construct()
    {
        $this->players = [new Player(), new Player()];
        $this->commerce = new Commerce();
        $this->tome = new Tome();

        fscanf(STDIN, "%d", $argc);
//        error_log($argc);
        for ($i = 0; $i < $argc; $i++) {

            $args = fscanf(STDIN, "%d %s %d %d %d %d %d %d %d %d %d");
//            error_log(implode(' ', $args));
            switch ($args[1]) {
                case 'BREW':
                    [$id, , $a, $b, $c, $d, $rupees, $bonus] = $args;
                    $this->commerce->orders[] = new Order($id, new Recipe($a, $b, $c, $d), $rupees, $bonus);
                    break;
                case 'CAST':
                    [$id, , $a, $b, $c, $d, , , , $active, $repeatable] = $args;
                    new Spell($id, new Recipe($a, $b, $c, $d), $this->players[0], $active, $repeatable);
                    break;
                case 'OPPONENT_CAST':
                    [$id, , $a, $b, $c, $d, , , , $active, $repeatable] = $args;
                    new Spell($id, new Recipe($a, $b, $c, $d), $this->players[1], $active, $repeatable);
                    break;
                case 'LEARN':
                    [$id, , $a, $b, $c, $d, , $index, $tax, , $repeatable] = $args;
                    $this->tome->spells[] = new TomeSpell($id, new Recipe($a, $b, $c, $d), $index, $tax, $repeatable);
                    break;
                default:
                    /* $id, $type, $a, $b, $c, $d, $rupees, $tomeIndex, $taxCount, $castable, $repeatable */
                    break;
            }
        }
        $this->commerce->sort();

        for ($i = 0; $i < 2; $i++) {
            [$a, $b, $c, $d, $rupees] = fscanf(STDIN, "%d %d %d %d %d");
//            error_log($a.' '.$b.' '.$c.' '.$d.' '.$rupees);
            $this->players[$i]->inventory = new Inventory($a, $b, $c, $d);
            $this->players[$i]->rupees = $rupees;
        }
    }

    public function launch(): string
    {
        $action = $this->bestAction();
        return (string)$action;
    }

    public function bestCast(): ?Action
    {
        $min = 5;
        $max = 5;
        $rupees = 0;
        $action = null;

        $castables = $this->players[0]->castables();
        foreach ($castables as $spell) {
            $repeat = 0;
            $nbRepeat = 1;
            $found = false;
            $spell->nbRepeat = 1;
            do {
                $repeat++;
                $action = new Action(Action::TYPE_CAST, $this, $spell);
                $action->process(Action::TYPE_CAST, min($min, $max));
                if ($action->iteration < $min && $action->rupees > $rupees) {
                    $min = $action->iteration;
                    $rupees = $action->rupees;
                    $nbRepeat = $repeat;
                    $found = true;
                }
            } while ($spell->repeatable && $spell->isCastable($repeat + 1));

            if ($found) {
                $spell->nbRepeat = $nbRepeat;
            }
        }

        return $action;
    }

    public function bestAction($type = ''): Action
    {
        $order = $this->commerce->best($this->players[0]->inventory);
        if ($order !== null) {
            return new Action(Action::TYPE_BREW, $this, $order);
        }

        $action = $this->bestCast();
        $min = $action->iteration;





        $actions = $this->actions();
        if (empty($actions)) {
            return new Action(Action::TYPE_WAIT, $this);
        }

        uasort(
            $actions,
            function (Action $a, Action $b) {
                $sort = $b->iteration <=> $a->iteration;
                if ($sort === 0) {
                    $sort = $a->rupees <=> $b->rupees;
                }

                return $sort;
            }
        );
        return $actions[0];
    }

    /**
     * @return Action[]
     */
    public function actions(): array
    {
        $actions = [];

        if ($this->iteration === 0) {
            // learn new free spell
            $spells = array_slice($this->tome->free($this->players[0]), 0, 1);
            foreach ($spells as $spell) {
                $actions[] = new Action(Action::TYPE_LEARN, $this, $spell);
            }
        }

        // execute spell
        $spells = array_slice($this->players[0]->bestSpell($this->players[0]->inventory, $this->commerce), 0, 1);
        foreach ($spells as $spell) {
            $actions[] = new Action(Action::TYPE_CAST, $this, $spell);
        }

        if (count($this->players[0]->toRest()) > 3 && $this->iteration < 2) {
            $actions[] = new Action(Action::TYPE_REST, $this);
        }

        // learn new spell
        $spells = array_slice($this->tome->best($this->players[0]->inventory), 0, 1);
        foreach ($spells as $spell) {
            $actions[] = new Action(Action::TYPE_LEARN, $this, $spell);
        }

        if (empty($actions)) {
            $actions[] = new Action(Action::TYPE_REST, $this);
        }

        return $actions;
    }
}

// game loop
while (true) {
//    $time = microtime(true);
    $step = new Step();
    echo $step->launch()."\n";
//    $time = microtime(true) - $time;
//    error_log('TIME: '. $time . ' secondes');
}