<?php

 function getValuesFromFile($filename){
  $result = array();
  if ($file_contents = file_get_contents($filename)){
   foreach (preg_split('/\n/', $file_contents) as $line){
    if (($line != "") and (!preg_match('/^\s*#/', $line)) and (!preg_match('/^\s*;/', $line)) and (!preg_match('/^\s*\/\//', $line))){
     $key = $line;
     $key = preg_replace('/\s*=.*/', '', $key);
     $key = preg_replace('/^\s*/', '', $key);
     $value = $line;
     $value = preg_replace("/.*$key\s*=\s*/", '', $value);
     $result[$key] = $value;
    };
   };
  };
  return $result;
 }

 function myStrToAssoc($string){
  $result = array();
  foreach (preg_split('/\s*,\s*/', $string) as $unit){
   list($key, $value) = preg_split('/\s*=>\s*/', $unit);
   $result[$key] = $value;
  };
  ksort($result);
  return $result;
 }

 function logging($message){
  print "$message\n";
 }


 // --------------------------------------------------------------------------------------------------
 class Location {
  protected $dependent = array();
  protected $country = "";
  protected $root_path;
  protected $country_path;
  public $cfg = array();
  
  public function __construct($root_path, $country){
   $this->root_path = $root_path;
   $this->country_path = $root_path;
   $this->setCountry($country);
  }
  
  public function setCountry($country){
   $country_path = $this->root_path.'/'.$country;
   if (file_exists($country_path) and is_dir($country_path)){
    $this->country_path = $country_path;
    $this->country = $country;
    $this->loadCFG();
    $this->informDependents();
   } else logging("Country path not found");
  }
  
  public function loadCFG(){
   $this->cfg = getValuesFromFile($this->getPath().'/key.value');
  }
 
  public function getCountry(){
   return $this->country;
  }
  
  public function getPath(){
   return $this->country_path;
  }
  
  // прайс упаковки в данной стране
  public function getPackagePrice(){
   if ($package = $this->cfg['package']) return myStrToAssoc($package);
  }
  
  public function addDependent($obj){
   $this->dependent[] = $obj;
  }

  public function informDependents(){
   foreach ($this->dependent as $obj){
    $obj->locationChangeEvent();
   };
  }
 }


 // --------------------------------------------------------------------------------------------------
 class PriceList {
  protected $location;
  protected $ingredients = array();
  protected $preparations = array();
  
  public function __construct(Location $location){
   $this->location = $location;
   $location->addDependent($this);
   $this->load();
  }

  public function load(){
   $this->loadIngredients();
   $this->loadPreparations();
  }

  public function loadIngredients(){
   $this->ingredients = array();
   $cfg = getValuesFromFile($this->location->getPath().'/ingredient.price');
   foreach ($cfg as $key => $value){
    list($ingredient, $amount, $cost) = preg_split('/\s*;\s*/', $value);
    $this->ingredients[$key] = array('ingredient' => $ingredient,  'amount' => $amount, 'cost' => $cost);
   };
  }
  
  public function loadPreparations(){
   $this->preparations = array();
   $cfg = getValuesFromFile($this->location->getPath().'/preparation.price');
   foreach ($cfg as $key => $value){
    list($preparation, $cost) = preg_split('/\s*;\s*/', $value);
    $this->preparations[$key] = array('preparation' => $preparation,  'cost' => $cost);
   };
  }
  
  public function locationChangeEvent(){
   $this->load();
  }
  
  public function getIngredientCost($ingredient_id, $amount){
   if (isset($this->ingredients[$ingredient_id])){
    $ingredient_array = $this->ingredients[$ingredient_id];
    $unit_cost = $ingredient_array['cost'] / $ingredient_array['amount'];
    $cost = $unit_cost * $amount;
    return $cost;
   } else logging("Ингредиент $ingredient_id не существует!");
   return 0;
  }
  
  public function getPreparationCost($preparation_id){
   if (isset($this->preparations[$preparation_id])){
    $preparation = $this->preparations[$preparation_id];
    return $preparation['cost'];
   } else logging("Способ приготовления $preparation_id не существует!");
   return 0;
  }
  
  public function getIngredients(){
   return $this->ingredients;
  }

  public function getPreparations(){
   return $this->preparations;
  }
 }


 // --------------------------------------------------------------------------------------------------
 class Ingredient {
  protected $price;
  protected $id;
  protected $amount;
  
  public function __construct(PriceList $price, $id, $amount){
   $this->price = $price;
   $this->id = $id;
   $this->amount = $amount;
  }
  
  public function getName(){
   $ingredients = $this->price->getIngredients();
   $id = $this->id;
   if (isset($ingredients[$id])){
    $ingredient = $ingredients[$id];
    return $ingredient['ingredient'];
   };
  }
  
  public function getCost(){
   return $this->price->getIngredientCost($this->id, $this->amount);
  }
  
  public function getAmount(){
   return $this->amount;
  }
 }


 // --------------------------------------------------------------------------------------------------
 class Preparation {
  protected $price;
  protected $id;
  
  public function __construct(PriceList $price, $id){
   $this->price = $price;
   $this->id = $id;
  }

  public function getName(){
   $preparations = $this->price->getPreparations();
   $id = $this->id;
   if (isset($preparations[$id])){
    $preparation = $preparations[$id];
    return $preparation['preparation'];
   };
  }

  public function getCost(){
   return $this->price->getPreparationCost($this->id);
  }
 }


 // --------------------------------------------------------------------------------------------------
 // блюдо
 class Dish {
  protected $price;
  protected $name = "undefined";
  protected $ingredients = array();
  protected $preparation;
  
  public function __construct(PriceList $price){
   $this->price = $price;
  }
  
  public function setName($name){
   $this->name = $name;
  }
  
  public function getName(){
   return $this->name;
  }
  
  public function addIngredient($ingredient_id, $amount){
   $ingredient = new Ingredient($this->price, $ingredient_id, $amount);
   if ($ingredient->getName()){
    $this->ingredients[] = $ingredient;
    return true;
   };
  }
  
  public function getIngredients(){
   return $this->ingredients;
  }
  
  public function delIngredient($id){
   if (isset($this->ingredients[$id])){
    unset($this->ingredients[$id]);
    $this->ingredients = array_values($this->ingredients);
    return true;
   };
  }
  
  public function setPreparation($preparation_id){
   $preparation = new Preparation($this->price, $preparation_id);
   if ($preparation->getName()){
    $this->preparation = $preparation;
    return true;
   };
  }
  
  public function getPreparation(){
   return $this->preparation;
  }
  
  public function getCost(){
   if (isset($this->preparation) and (count($this->ingredients) > 0)){
    $sum = $this->preparation->getCost();
    foreach ($this->ingredients as $ingredient){
     $sum = $sum + $ingredient->getCost();
    };
    return $sum;
   } else logging("У блюда ".$this->getName()." отсутствуют ингдидиенты или способ приготовления!");
   return 0;
  }
  
  public function loadFromFile($filename){
   $cfg = getValuesFromFile($filename);
   if (isset($cfg['name'])) $this->setName($cfg['name']);
   if (isset($cfg['preparation'])) $this->setPreparation($cfg['preparation']);
   if (isset($cfg['ingredients'])){
    $ingredients = myStrToAssoc($cfg['ingredients']);
    foreach ($ingredients as $id => $amount){
     $this->addIngredient($id, $amount);
    };
   };
  }
 }


 // --------------------------------------------------------------------------------------------------
 class ComplexOrder {
  protected $price;
  protected $location;
  protected $name;
  protected $dishes = array();
  protected $cost;  // если присвоено, то в качестве цены просто выдается это значение (фиксированая цена комплексного обеда)
  protected $discount;  // скидка на комплексный обед
  protected $time_str;  // диапазон времени
  
  public function __construct(PriceList $price, Location $location, $filename){
   $this->price = $price;
   $this->location = $location;
   $this->loadComplexOrderFromFile($filename);
  }
  
  protected function loadComplexOrderFromFile($filename){
   $filename = $this->location->getPath().'/'.$filename;
   $cfg = getValuesFromFile($filename);
   if (isset($cfg['name'])) $this->name = $cfg['name'];
   if (isset($cfg['time'])) $this->time_str = $cfg['time'];
   if (isset($cfg['discount'])) $this->discount = $cfg['discount'];
   if (isset($cfg['cost'])) $this->cost = $cfg['cost'];
   if (isset($cfg['dishes'])){
    foreach (preg_split('/\s*,\s*/', $cfg['dishes']) as $dish_filename){
     $dish_filename = $this->location->getPath().'/'.$dish_filename;
     $dish = new Dish($this->price);
     $dish->loadFromFile($dish_filename);
     $this->dishes[] = $dish;
    };
   };
  }
  
  public function checkTimeIntreval(){
   if (isset($this->time_str)){
    list($start_str, $end_str) = preg_split('/\s*-\s*/', $this->time_str);
    $start_str = date('Y-m-d').' '.$start_str.':00';
    $end_str = date('Y-m-d').' '.$end_str.':00';
    if ((strtotime($start_str) <= strtotime('now')) and (strtotime('now') < strtotime($end_str))) return true;
   };
  }
  
  public function getName(){
   return $this->name;
  }
  
  public function getDiscount(){
   if ((isset($this->discount)) and (!isset($this->cost)) and ($this->checkTimeIntreval())) return $this->discount;
   return 0;
  }
  
  public function getCost(){
   if ((isset($this->cost)) and ($this->checkTimeIntreval())){
    return $this->cost;
   } else {
    $cost = 0;
    foreach ($this->dishes as $dish){
     $cost = $cost + $dish->getCost();
    };
    return $cost;
   };
  }
 }


 // --------------------------------------------------------------------------------------------------
 // предпологаем, что дисконтная карта накопительная, и сумма скидки зависит от общей стоимости зафиксированных покупок
 class DiscountCard {
  protected $url;
  protected $name;
  protected $discount;
  protected $sum;
  
  public function __construct($url){
   $this->url = $url;
   $this->load();
  }
  
  public function load(){
   $cfg = getValuesFromFile($this->url);
   $this->name = $cfg['name'];
   $this->discount = $cfg['discount'];
   $this->sum = $cfg['sum'];
  }
  
  public function getDiscount(){
   $result = 0;
   if (isset($this->discount) and isset($this->sum)){
    $discount = myStrToAssoc($this->discount);
    foreach ($discount as $key => $value){
     if ($this->sum >= $key) $result = $value;
    };
   } else logging("Нет информации по скидке на карте");
   return $result;
  }

  public function getDiscountSum($sum){
   $discount = $this->getDiscount();
   if ($discount == 0) return 0;
   return ($sum / 100 * $discount);
  }

  public function apply($sum){
   if (is_writable($this->url)){
    $fh = fopen($this->url, 'w');
    fwrite($fh, 'name = '.$this->name."\n"); 
    fwrite($fh, 'discount = '.$this->discount."\n");
    fwrite($fh, 'sum = '.($this->sum + $sum)."\n");
    fclose($fh);
    $this->load();
    return true;
   } else logging("Неозможно записать данные в файл дисконтной карты!");
  }
 }


 // --------------------------------------------------------------------------------------------------
 class Order {
  // ! комплексный обед фиксированой цены, или со скидкой
  // ! упаковка зависит от страны или суммы
  // ! дисконтная карта
  protected $location;
  protected $dishes = array();
  protected $complex_order;
  protected $discount_card;
  
  public function __construct(Location $location){
   $this->location = $location;
   $location->addDependent($this);
  }

  public function locationChangeEvent(){
   $this->dishes = array();
   unset($complex_order);
   unset($discount_card);
  }
  
  public function addDish(Dish $dish){
   $this->dishes[] = $dish;
   return true;
  }
  
  public function getDishes(){
   return $this->dishes;
  }
  
  public function delDish($id){
   if (isset($this->dishes[$id])){
    unset($this->dishes[$id]);
    $this->dishes = array_values($this->dishes);
    return true;
   };
  }
  
  public function setComplexOrder(ComplexOrder $order){
   $this->complex_order = $order;
   return true;
  }

  public function delComplexOrder(){
   unset($this->complex_order);
   return true;
  }

  public function setDiscountCard(DiscountCard $card){
   $this->discount_card = $card;
   return true;
  }
  
  public function delDiscountCard(){
   unset($this->discount_card);
   return true;
  }
  
  public function getCost(){
   //$cost = $this->getDishesCost() + $this->getComplexOrderCost(); // вычисляем стоимость блюд + комплексного обеда
   //$cost = $cost - $this->getComplexOrderDiscount($cost);  // отнимаем сумму скидки комплексного обеда; если скидка на комплексный обед установлена - она дожна учитывать весь заказ, а не только комплексное меню
   //$cost = $cost + $this->getPackageCost($cost); // добавляем стоимость упаковки
   //$cost = $cost - $this->getDiscountCardDiscountSum($cost); // отнимает скидку по дисконтной карте
   return $this->getCostAfterDiscountCardDiscount();
  }
  
  public function getDishesCost(){
   $cost = 0;
   foreach ($this->dishes as $dish){
    $cost = $cost + $dish->getCost();
   };
   return $cost;
  }
  
  public function getComplexOrder(){
   if (isset($this->complex_order)) return $this->complex_order;
  }
  

  public function getComplexOrderCost(){
   $cost = 0;
   if (isset($this->complex_order)) $cost = $cost + $this->complex_order->getCost();
   return $cost;
  }

  public function getDishesPlusComplexOrderCost(){
   return ($this->getDishesCost() + $this->getComplexOrderCost());
  }

  public function getComplexOrderDiscount($sum){
   $result = 0;
   if (isset($this->complex_order)){
    $discount = $this->complex_order->getDiscount();
    if ($discount != 0) $result = $sum / 100 * $discount;
   };
   return $result;
  }

  public function getCostAfterComplexDiscount(){
   $sum = $this->getDishesPlusComplexOrderCost();
   return ($sum - $this->getComplexOrderDiscount($sum));
  }
  
  public function getPackageCost(){
   $result = 0;
   $order_sum = $this->getCostAfterComplexDiscount();
   if ($price_array = $this->location->getPackagePrice()){
    foreach ($price_array as $key => $value){
     if ($order_sum >= $key) $result = $value;
    };
   } else logging("Не установлен тариф на упаковку");
   return $result;
  }
  
  public function getCostAfterPackage(){
   return ($this->getCostAfterComplexDiscount() + $this->getPackageCost());
  }
  
  public function getDiscountCardDiscount(){
   if (isset($this->discount_card)) return $this->discount_card->getDiscount();
   return 0;
  }
  
  public function getDiscountCardDiscountSum(){
   $discount = $this->getDiscountCardDiscount();
   if ($discount != 0) return ($this->getCostAfterPackage() / 100 * $discount);
   return 0;
  }
  
  public function getCostAfterDiscountCardDiscount(){
   return ($this->getCostAfterPackage() - $this->getDiscountCardDiscountSum());
  }
 }


 class Input {
  protected $prompt = "";
  protected $acceptedValues = array();
  
  public function setPrompt($prompt){
   $this->prompt = $prompt;
   return true;
  }
  
  public function getAcceptedValues(){
   return $this->acceptedValues;
  }
  
  public function setAcceptedValue($key, $value){
   $this->acceptedValues[$key] = $value;
   return true;
  }

  public function delAcceptedValue($key){
   if (isset($this->acceptedValues[$key])){
    unset($this->acceptedValues[$key]);
    return true;
   };
  }

  public function getInput(){
   print $this->prompt;
   $input = trim(fgets(STDIN), "\n ");
   if ($this->isAcceptable($input)) return $this->getReturnValue($input);
   return $this->getInput();
  }
  
  public function isAcceptable($input){
   if ((count($this->acceptedValues) == 0) or (isset($this->acceptedValues[$input]))) return true;
  }
  
  public function getReturnValue($input){
   if (isset($this->acceptedValues[$input])) return $this->acceptedValues[$input];
   return $input;
  }
 }



 class MainInterface {
  protected $location;
  protected $price;
  protected $order;
 
  public function __construct($root_path, $country){
   $this->location = new Location($root_path, $country);
   $this->price = new PriceList($this->location);
   $this->order = new Order($this->location);
   $this->rootLevel();
  }
  
  public function rootLevel(){
   $prompt = new Input();
   $prompt->setPrompt("Выберите действие: ");
   $prompt->setAcceptedValue('q', 'quit');
   $prompt->setAcceptedValue('quit', 'quit');
   $prompt->setAcceptedValue('a', 'add_dish');
   $prompt->setAcceptedValue('add', 'add_dish');
   $prompt->setAcceptedValue('d', 'del_dish');
   $prompt->setAcceptedValue('del', 'del_dish');
   $prompt->setAcceptedValue('e', 'edit_dish');
   $prompt->setAcceptedValue('edit', 'edit_dish');
   $prompt->setAcceptedValue('s', 'set_complex_order');
   $prompt->setAcceptedValue('u', 'del_complex_order');
   $prompt->setAcceptedValue('f', 'set_discount_card');
   $prompt->setAcceptedValue('g', 'set_discount_card');
   $prompt->setAcceptedValue('r', 'reload');
   do {
    $this->printInfo();
    print "\n";
    print "a, add  => добавть блюдо\n";
    print "d, del  => удалить блюдо\n";
    print "e, edit => редактировать блюдо\n";
    print "s       => выбрать комплексный обед\n";
    print "u       => отменить комплексный обед\n";
    print "f       => выбрать дисконтную карту\n";
    print "g       => убрать дисконтную карту\n";
    print "q, quit => выход\n";
    $input = $prompt->getInput();
    if ($input == 'add_dish') $this->addDish();
    if ($input == 'del_dish') $this->delDish();
    if ($input == 'edit_dish') $this->selectAndEditDish();
    if ($input == 'set_complex_order') $this->setComplexOrder();
    if ($input == 'del_complex_order') $this->delComplexOrder();
    if ($input == 'set_discount_card') $this->setDiscountCard();
    if ($input == 'del_discount_card') $this->delDiscountCard();
   } while ($input !== "quit");
  }
  
  public function addDish(){
   print "\n";
   $path = $this->location->getPath();
   if ($dh = opendir($path)){
    $i = 1;
    $prompt = new Input();
    $prompt->setPrompt("Введите номер блюда или 'q' для возврата: ");
    $prompt->setAcceptedValue('q', 'quit');
    $prompt->setAcceptedValue('quit', 'quit');
    while (($file = readdir($dh)) !== false){
     if (preg_match('/^[^\.].*\.dish$/', $file)){
      $file_contents = getValuesFromFile($path."/".$file);
      print $i." => ".$file_contents['name']."\n";
      $prompt->setAcceptedValue($i, $file);
      $i++;
     };
    };
    closedir($dh);
    do {
     $input = $prompt->getInput();
     if (file_exists($path.'/'.$input)){
      $dish = new Dish($this->price);
      $dish->loadFromFile($path.'/'.$input);
      $this->order->addDish($dish);
      print "Блюдо добавлено в заказ.\n";
     };
    } while ($input !== "quit");
   } else logging("Не могу прочесть директорию!");
  }
  
  public function delDish(){
   $dishes = $this->order->getDishes();
   if (count($dishes) > 0){
    $i = 1;
    $prompt = new Input();
    $prompt->setPrompt("Введите номер блюда или 'q' для возврата: ");
    $prompt->setAcceptedValue('q', 'quit');
    $prompt->setAcceptedValue('quit', 'quit');
    foreach ($dishes as $dish){
     print "$i => ".$dish->getName()."\n";
     $prompt->setAcceptedValue($i, ($i - 1));
     $i++;
    };
    do {
     $input = $prompt->getInput();
     if (isset($dishes[$input])){
      $this->order->delDish($input);
      print "Блюдо удалено из заказа!\n";
      $input = "quit";
     };
    } while ($input !== "quit");
   } else print "В заказе нет блюд!\n";
  }

  public function editDish($dish){
   if ($dish){ print $dish->getName()."\n"; } else {
    $dish = new Dish($this->price);
    $this->order->addDish($dish);
   };
   $prompt = new Input();
   $prompt->setPrompt("Введите действие: ");
   $prompt->setAcceptedValue('q', 'quit');
   $prompt->setAcceptedValue('quit', 'quit');
   $prompt->setAcceptedValue('a', 'add_ingedient');
   $prompt->setAcceptedValue('d', 'del_ingredient');
   $prompt->setAcceptedValue('s', 'set_preparation');
   do {
    print "Ингредиенты:\n";
    $ingredients = $dish->getIngredients();
    foreach ($ingredients as $ingredient){
     print " ".$ingredient->getName()." / ".$ingredient->getAmount()." / ".$ingredient->getCost()."\n";
    }
    print "Способ приготовления: ".$dish->getPreparation()->getName()." (".$dish->getPreparation()->getCost().")\n";
    if ((count($ingredients) == 0) or (!$dish->getPreparation()->getName())) print "\nНет ингредиентов или не установлен способ приготовления!\n";
    print "\n";
    print "a => добавить ингредиент\n";
    print "d => удалить ингредиент\n";
    print "s => выбрать способ приготовления\n";
    print "q => возврат\n";
    $input = $prompt->getInput();
    if ($input == 'add_ingedient') $this->addIngredientToDish($dish);
    if ($input == 'del_ingredient'){ $this->delIngredientFromDish($dish); };
    if ($input == 'set_preparation'){ $this->setDishPreparation($dish); };
   } while ($input !== "quit");
  }
  
  public function setDishPreparation($dish){
   $prompt = new Input();
   $prompt->setPrompt("Выберите метод или 'q' для возврата: ");
   $prompt->setAcceptedValue('q', 'quit');
   $prompt->setAcceptedValue('quit', 'quit');
   $preparations = $this->price->getPreparations();
   foreach ($preparations as $id => $preparation){
    print $id." => ".$preparation['preparation']." / ".$preparation['cost']."\n";
    $prompt->setAcceptedValue($id, $id);
   };
   do {
    $input = $prompt->getInput();
    if (isset($preparations[$input])){
     $dish->setPreparation($input);
     print "\nМетод приготовления установлен!\n";
     $input = "quit";
    };
   } while ($input !== "quit");
  }
  
  public function delIngredientFromDish(Dish $dish){
   $prompt = new Input();
   $prompt->setPrompt("Выберите ингредиент или 'q' для возврата: ");
   $prompt->setAcceptedValue('q', 'quit');
   $prompt->setAcceptedValue('quit', 'quit');
   $ingredients = $dish->getIngredients();
   print "\nИнгредиенты: \n";
   foreach ($ingredients as $id => $ingredient){
    print "$id => ".$ingredient->getName()." / ".$ingredient->getAmount()." / ".$ingredient->getCost()."\n";
    $prompt->setAcceptedValue($id, $id);
   };
   do {
    $input = $prompt->getInput();
    if (isset($ingredients[$input])){
     $dish->delIngredient($input);
     print "\nИнгредиент удален!\n";
     $input = "quit";
    };
   } while ($input !== "quit");
   print "\n\n";
  }
  
  public function addIngredientToDish(Dish $dish){
   $prompt = new Input();
   $prompt->setPrompt("Введите номер ингредиеента или 'q' для возврата: ");
   $prompt->setAcceptedValue('q', 'quit');
   $prompt->setAcceptedValue('quit', 'quit');
   $ingredients_price = $this->price->getIngredients();
   print "\n";
   print "номер => название / норма / стоимость\n";
   foreach ($ingredients_price as $key => $value){
    print $key." => ".$value['ingredient']." ".$value['amount']." ".$value['cost']."\n";
    $prompt->setAcceptedValue($key, $key);
   };
   do {
    $input = $prompt->getInput();
    if (isset($ingredients_price[$input])){
     print $ingredients_price[$input]['ingredient']."\n";
     $prompt_amount = new Input();
     $prompt_amount->setPrompt("Введите колличество: ");
     do {
      $amount =  $prompt_amount->getInput();
     } while (!preg_match('/^[\d.]+$/', $amount));
     $dish->addIngredient($input, $amount);
    };
   } while ($input !== "quit");
  }

  public function selectAndEditDish(){
   $dishes = $this->order->getDishes();
   if (count($dishes) > 0){
    $i = 1;
    $prompt = new Input();
    $prompt->setPrompt("Введите номер блюда или 'q' для возврата: ");
    $prompt->setAcceptedValue('q', 'quit');
    $prompt->setAcceptedValue('quit', 'quit');
    foreach ($dishes as $dish){
     print "$i => ".$dish->getName()."\n";
     $prompt->setAcceptedValue($i, ($i - 1));
     $i++;
    };
    do {
     $input = $prompt->getInput();
     if (isset($dishes[$input])){
      $this->editDish($dishes[$input]);
      //$this->order->delDish($input);
      //print "Блюдо удалено из заказа!\n";
      $input = "quit";
     };
    } while ($input !== "quit");
   } else print "В заказе нет блюд!\n";
  }

  public function setComplexOrder(){
   print "\n";
   $path = $this->location->getPath();
   if ($dh = opendir($path)){
    $i = 1;
    $prompt = new Input();
    $prompt->setPrompt("Введите номер блюда или 'q' для возврата: ");
    $prompt->setAcceptedValue('q', 'quit');
    $prompt->setAcceptedValue('quit', 'quit');
    while (($file = readdir($dh)) !== false){
     if (preg_match('/^[^\.].*\.complex$/', $file)){
      $file_contents = getValuesFromFile($path."/".$file);
      print $i." => ".$file_contents['name']."\n";
      $prompt->setAcceptedValue($i, $file);
      $i++;
     };
    };
    closedir($dh);
    do {
     $input = $prompt->getInput();
     if (file_exists($path.'/'.$input)){
      $complex_order = new ComplexOrder($this->price, $this->location, $input);
      $this->order->setComplexOrder($complex_order);
      print "Выбран комплексный заказ.\n";
      $input = "quit";
     };
    } while ($input !== "quit");
   } else logging("Не могу прочесть директорию!");
  }
  
  public function delComplexOrder(){
   $this->order->delComplexOrder();
  }
  
  public function setDiscountCard(){
   print "\n";
   $path = $this->location->getPath();
   if ($dh = opendir($path)){
    $i = 1;
    $prompt = new Input();
    $prompt->setPrompt("Введите номер карты или 'q' для возврата: ");
    $prompt->setAcceptedValue('q', 'quit');
    $prompt->setAcceptedValue('quit', 'quit');
    while (($file = readdir($dh)) !== false){
     if (preg_match('/^[^\.].*\.card$/', $file)){
      $file_contents = getValuesFromFile($path."/".$file);
      print $i." => ".$file_contents['name']."\n";
      $prompt->setAcceptedValue($i, $file);
      $i++;
     };
    };
    closedir($dh);
    do {
     $input = $prompt->getInput();
     if (file_exists($path.'/'.$input)){
      $discount_card = new DiscountCard($path.'/'.$input);
      $this->order->setDiscountCard($discount_card);
      print "Выбрана дисконтная карта!\n";
      $input = "quit";
     };
    } while ($input !== "quit");
   } else logging("Не могу прочесть директорию!");
  }
  
  public function getInfo(){
   $result = "\n";
   $result .= "+---------------------------------------\n";
   $result .= "Страна: ".$this->location->getCountry()."\n";
   $result .= "Заказ (".$this->order->getDishesCost()."):\n";
   foreach ($this->order->getDishes() as $dish){
    $result .= " ".$dish->getName()." / ".$dish->getCost()."\n";
   };
   if ($complex_order = $this->order->getComplexOrder()){
    $result .= "Комплексный заказ: ".$complex_order->getName()." / ";
    if ($complex_order->checkTimeIntreval()){ $result .= "скидка ".$complex_order->getDiscount()." от суммы всего заказа"; } else { $result .= "не льготное время"; };
    $result .= " / ".$complex_order->getCost()."\n";
   };
   $result .= "Сумма с учетом скидки комплексного заказа: ".$this->order->getCostAfterComplexDiscount()."\n";
   $result .= "Стоимость упаковки: ".$this->order->getPackageCost()."\n";
   $result .= "Стоимость после упаковки: ".$this->order->getCostAfterPackage()."\n";
   $result .= "Дисконтная карта: ".$this->order->getDiscountCardDiscount()." (".$this->order->getDiscountCardDiscountSum().")\n";
   $result .= "Итог: ".$this->order->getCost()."\n";
   return $result;
  }
  
  public function printInfo(){
   print $this->getInfo();
  }
 }


 $main = new MainInterface(getcwd(), 'Belarus');

?>