<?php


class TeestShopCommand extends AbstractCommand
{


    public function actionInit()
    {

        Yii::app()->mongodb->setDB('newTestDb');
        Yii::app()->mongodb->selectCollection('user')->drop();
        Yii::app()->mongodb->selectCollection('product')->drop();
        Yii::app()->mongodb->selectCollection('order')->drop();


        Yii::app()->mongodb->setDB('newTestDb');
        $userCollection = Yii::app()->mongodb->selectCollection('user');
        $productCollection = Yii::app()->mongodb->selectCollection('product');
        $orderCollection = Yii::app()->mongodb->selectCollection('order');


        $productData = [
            ['_id' => '1', 'name' => 'T-shirt', 'description' => '40% off anti-season Korean direct mail department store authentic purchase down jacket goose down jacket', 'price' => 43],
            ['_id' => '2', 'name' => 'Coat', 'description' => '40% off anti-season direct mail department store genuine purchasing ', 'price' => 25],
            ['_id' => '3', 'name' => 'Suit', 'description' => 'Korean direct mail department store genuine purchasing men', 'price' => 20],
        ];

        foreach ($productData as $product) {
            $productCollection->insert($product);
        }


        $orderData = [];
        foreach ($orderData as $order) {
            $orderCollection->insert($order);
        }


        $userData = [
            ['name' => 'newOne', 'pass' => 'qwe'],
            ['name' => 'newTwo', 'pass' => 'qwe'],
            ['name' => 'newThree', 'pass' => 'qwe']
        ];

        foreach ($userData as $user) {
            $userCollection->insert($user);
        }

        Yii::app()->redis->getClient()->setOption(Redis::OPT_PREFIX, 'shop_card.');
        $redisCards = Yii::app()->redis->getClient()->keys('*');
        if ($redisCards) {
            foreach ($redisCards as $card) {
                $card = mb_substr($card, 10);
                $members = Yii::app()->redis->getClient()->sMembers($card);
                Yii::app()->redis->getClient()->sRem($card, ...$members);
            }
        }

    }


    public function actionLogin($user, $pass)
    {
        Yii::app()->redis->getClient()->setOption(Redis::OPT_PREFIX, '');
        Yii::app()->mongodb->setDB('newTestDb');
        $userCollection = Yii::app()->mongodb->selectCollection('user');
        $userName = $userCollection->findOne(['name' => $user]);

        if (Yii::app()->redis->getClient()->get(md5($userName['name']))) {
            echo "Вы уже авторизованы под данным логином, можете зайти под другим \n";
            return false;
        }

        if ($userName) {
            $user = $userCollection->findOne(['name' => $user, 'pass' => $pass]);
            if ($user) {
                $userKey = md5($user['name']);
                Yii::app()->redis->getClient()->set($userKey, $user['name'], 3000);
                echo "Вы успешно авторизовались, Ваш ключ: " . $userKey . PHP_EOL;
                return true;
            }
            echo "Неверный пароль\n";
            return false;
        }
        echo "Такого пользователя не существует\n";
        return false;
    }


    public function actionLogout($userKey)
    {
        Yii::app()->redis->getClient()->setOption(Redis::OPT_PREFIX, '');
        if ($userKey) {
            Yii::app()->redis->getClient()->del($userKey);
            echo "Вы вышли\n";
            return true;
        }
        echo "Авторизуйтесь\n";
        return false;
    }


    public function actionProductList()
    {
        Yii::app()->mongodb->setDB('newTestDb');
        $productCollection = Yii::app()->mongodb->selectCollection('product');
        $data = $productCollection->find();
        foreach ($data as $product) {
            print_r($product['name'] . ' id ' . $product['_id'] . "\n");
        }
    }


    public function actionAddToCard($userKey, $product)
    {
        Yii::app()->redis->getClient()->setOption(Redis::OPT_PREFIX, '');
        Yii::app()->mongodb->setDB('newTestDb');
        $userCollection = Yii::app()->mongodb->selectCollection('user');
        $add = Yii::app()->redis->getClient()->get($userKey);
        $userName = $userCollection->findOne(['name' => $add]);

        if ($userName) {
            $userName = mb_substr($userKey, 0, 10);
            Yii::app()->mongodb->setDB('newTestDb');
            $productName = Yii::app()->mongodb->selectCollection('product')->findOne(['name' => $product]);
            if ($productName) {
                Yii::app()->redis->getClient()->sAdd($userName, $productName['_id']);
                Yii::app()->redis->getClient()->expire($userName, 3600);
                echo "Товар добавлен \n";
                return true;
            }
            echo "Такого товара нет\n";
            return false;
        }
        echo "Авторизуйтесь\n";
        return false;
    }


    public function actionRemoveFromCard($userKey, $product)
    {
        Yii::app()->redis->getClient()->setOption(Redis::OPT_PREFIX, '');
        Yii::app()->mongodb->setDB('newTestDb');
        $userCollection = Yii::app()->mongodb->selectCollection('user');

        if ($userKey) {
            $add = Yii::app()->redis->getClient()->get($userKey);
            $userName = $userCollection->findOne(['name' => $add]);
        }

        if ($userName) {
            $redisCard = mb_substr($userKey, 0, 10);
            $members = Yii::app()->redis->getClient()->sMembers($redisCard);
            if ($members) {
                Yii::app()->mongodb->setDB('newTestDb');
                $productName = Yii::app()->mongodb->selectCollection('product')->findOne(['name' => $product]);
                Yii::app()->redis->getClient()->sRem($redisCard, $productName['_id']);
                echo "Продукт удален \n";
                return true;
            }
            echo "Ваша корзина пуста\n";
            return false;
        }
        echo "Вы еще не авторизовались";
        return false;
    }

    public function actionBuy($userKey)
    {
        Yii::app()->redis->getClient()->setOption(Redis::OPT_PREFIX, '');
        Yii::app()->mongodb->setDB('newTestDb');
        $userCollection = Yii::app()->mongodb->selectCollection('user');

        if ($userKey) {
            $add = Yii::app()->redis->getClient()->get($userKey);
            $userName = $userCollection->findOne(['name' => $add]);
        }

        if ($userName) {
            $redisCard = mb_substr($userKey, 0, 10);
            $members = Yii::app()->redis->getClient()->sMembers($redisCard);
            if ($members) {
                Yii::app()->mongodb->setDB('newTestDb');
                $productCollection = Yii::app()->mongodb->selectCollection('product')->find();
                $price = 0;
                echo "Список товаров в вашей корзине:\n";
                foreach ($productCollection as $product) {
                    if (in_array($product['_id'], $members)) {
                        echo $product['name'] . ' Цена:' . $product['price'] . '$' . "\n";
                        $price += $product['price'];
                    }
                }
                echo "Хотите совершить покупку y/n: ";
                $confirm = readline();
                if ($confirm == 'n') {
                    echo "Покупка отменена\n";
                    return false;
                }
                if ($confirm == 'y') {
                    $orderedProducts = [
                        'date' => new DateTime(),
                        'price' => $price,
                        'product' => $members,
                        'user' => md5($userName['name']),
                    ];
                    Yii::app()->mongodb->selectCollection('order')->insert($orderedProducts);
                    echo "Покупка совершена\n";

                    if ($redisCard) {
                        Yii::app()->redis->getClient()->sRem($redisCard, ...$members);
                    }
                    return true;
                }
            }
            echo "Ваша корзина пуста\n";
            return false;
        }
        echo "Авторизуйтесь \n";
        return false;
    }

    public function actionOrderList($userKey)
    {
        Yii::app()->redis->getClient()->setOption(Redis::OPT_PREFIX, '');
        Yii::app()->mongodb->setDB('newTestDb');
        $userCollection = Yii::app()->mongodb->selectCollection('user');

        if ($userKey) {
            $add = Yii::app()->redis->getClient()->get($userKey);
            $userName = $userCollection->findOne(['name' => $add]);
        }

        if ($userName) {
            $userName = md5($userName['name']);
            echo "Список заказов: \n";
            Yii::app()->mongodb->setDB('newTestDb');
            $orderCollection = Yii::app()->mongodb->selectCollection('order')->find(['user' => $userName]);
            foreach ($orderCollection as $order) {
                echo 'id заказа: ' . $order['_id'] . ' / дата заказа: ' . $order['date']['date'] . ' / цена: ' . $order['price'] . "\n";
            }
            return true;
        }
        echo "Авторизуйтесь\n";
        return false;
    }


    public function actionOrderInfo($userKey, $order)
    {
        Yii::app()->redis->getClient()->setOption(Redis::OPT_PREFIX, '');
        Yii::app()->mongodb->setDB('newTestDb');
        $userCollection = Yii::app()->mongodb->selectCollection('user');

        if ($userKey) {
            $add = Yii::app()->redis->getClient()->get($userKey);
            $userName = $userCollection->findOne(['name' => $add]);
        }

        if ($userName) {
            $userName = $userName['name'];
            Yii::app()->mongodb->setDB('newTestDb');
            $orderCollection = Yii::app()->mongodb->selectCollection('order')->find(['_id' => new MongoId($order)]);

            $arrayKeys = [];
            foreach ($orderCollection as $orderAbout) {
                $arrayKeys[] = $orderAbout;
                echo 'Идентификатор:  ' . $order . PHP_EOL . 'Заказчик: ' . $userName . PHP_EOL . 'Дата заказа: ' . $orderAbout['date']['date'] . PHP_EOL . 'Цена: ' . $orderAbout['price'] . PHP_EOL . 'Перечень товаров: ' . PHP_EOL;
            }

            foreach ($arrayKeys as $key => $orderAbout) {
            }
            $productCollection = Yii::app()->mongodb->selectCollection('product')->find();
            foreach ($productCollection as $product) {
                if (in_array($product['_id'], $arrayKeys[$key]['product'])) {
                    $productArray[] = $product['name'];
                }
            }
            echo implode(', ', $productArray) . PHP_EOL;
            return true;
        }
        echo "Авторизуйтесь\n";
        return false;
    }


}


