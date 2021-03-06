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
            echo "???? ?????? ???????????????????????? ?????? ???????????? ??????????????, ???????????? ?????????? ?????? ???????????? \n";
            return false;
        }

        if ($userName) {
            $user = $userCollection->findOne(['name' => $user, 'pass' => $pass]);
            if ($user) {
                $userKey = md5($user['name']);
                Yii::app()->redis->getClient()->set($userKey, $user['name'], 3000);
                echo "???? ?????????????? ????????????????????????????, ?????? ????????: " . $userKey . PHP_EOL;
                return true;
            }
            echo "???????????????? ????????????\n";
            return false;
        }
        echo "???????????? ???????????????????????? ???? ????????????????????\n";
        return false;
    }


    public function actionLogout($userKey)
    {
        Yii::app()->redis->getClient()->setOption(Redis::OPT_PREFIX, '');
        if ($userKey) {
            Yii::app()->redis->getClient()->del($userKey);
            echo "???? ??????????\n";
            return true;
        }
        echo "??????????????????????????\n";
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
                echo "?????????? ???????????????? \n";
                return true;
            }
            echo "???????????? ???????????? ??????\n";
            return false;
        }
        echo "??????????????????????????\n";
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
                echo "?????????????? ???????????? \n";
                return true;
            }
            echo "???????? ?????????????? ??????????\n";
            return false;
        }
        echo "???? ?????? ???? ????????????????????????????";
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
                echo "???????????? ?????????????? ?? ?????????? ??????????????:\n";
                foreach ($productCollection as $product) {
                    if (in_array($product['_id'], $members)) {
                        echo $product['name'] . ' ????????:' . $product['price'] . '$' . "\n";
                        $price += $product['price'];
                    }
                }
                echo "???????????? ?????????????????? ?????????????? y/n: ";
                $confirm = readline();
                if ($confirm == 'n') {
                    echo "?????????????? ????????????????\n";
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
                    echo "?????????????? ??????????????????\n";

                    if ($redisCard) {
                        Yii::app()->redis->getClient()->sRem($redisCard, ...$members);
                    }
                    return true;
                }
            }
            echo "???????? ?????????????? ??????????\n";
            return false;
        }
        echo "?????????????????????????? \n";
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
            echo "???????????? ??????????????: \n";
            Yii::app()->mongodb->setDB('newTestDb');
            $orderCollection = Yii::app()->mongodb->selectCollection('order')->find(['user' => $userName]);
            foreach ($orderCollection as $order) {
                echo 'id ????????????: ' . $order['_id'] . ' / ???????? ????????????: ' . $order['date']['date'] . ' / ????????: ' . $order['price'] . "\n";
            }
            return true;
        }
        echo "??????????????????????????\n";
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
                echo '??????????????????????????:  ' . $order . PHP_EOL . '????????????????: ' . $userName . PHP_EOL . '???????? ????????????: ' . $orderAbout['date']['date'] . PHP_EOL . '????????: ' . $orderAbout['price'] . PHP_EOL . '???????????????? ??????????????: ' . PHP_EOL;
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
        echo "??????????????????????????\n";
        return false;
    }


}


