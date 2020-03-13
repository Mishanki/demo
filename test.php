<?php

/*
Требования к решению
Решение необходимо оформить в виде ссылки на github | gist | codebunk

Условие
Разработчика попросили получить данные из REST-API стороннего сервиса. Данные необходимо было кешировать, ошибки логировать. Разработчик с задачей справился, ниже предоставлен его код.

Задание:

Проведите максимально подробный Code Review. Необходимо написать, с чем вы не согласны и почему.
Исправьте обозначенные ошибки, предоставив свой вариант кода.
*/

/**
 *  Code Review
 *  $host, $user, $password - эти данные нужно выносить в отдельный конфиг
 *  $this->logger->critical('Error'); Сообщение об ошибке не отображает детализацию
 *  Все данные кешируются железно на 1 день
 *  Если потребуется срочно получить свежие данные, то у нас такой возможности
 *  Ключь для кеширования не содержит информации о пользователе, ip-адресе, вызываемом методе
 *  Если у нас один обработчик логов, то его можно вынести в конструктор $this->logger = $logger
 *  В методах не указаны типы возвращаемых данных
 *  Не взде есть PHPDoc
 */

use DateTime;
use Exception;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Log\LoggerInterface;
use Yii;

class DataProvider
{
    private $host;
    private $user;
    private $password;

    /**
     * DataProvider constructor.
     */
    public function __construct()
    {
        // Загрузка из конфига.
        $this->host = Yii::$app->params['host'];
        $this->user = Yii::$app->params['user'];
        $this->password = Yii::$app->params['password'];
    }

    /**
     * @param array $request
     *
     * @return array
     */
    public function get(array $request): array
    {
        // returns a response from external service
    }
}

class DecoratorManager extends DataProvider
{
    public $cache;
    public $duration;
    public $logger;

    /**
     * @param CacheItemPoolInterface $cache
     * @param LoggerInterface $logger
     * @throws \Exception
     */
    public function __construct(CacheItemPoolInterface $cache, LoggerInterface $logger)
    {
        parent::__construct();
        $this->cache = $cache;
        $this->setCacheDuration(3600); // дефолтное значение кэша
        $this->logger = $logger; // если сущность логгера одна, то setLogger метод становится лишним
    }

    /**
     * @param array $input
     * @return array
     */
    public function getResponse(array $input): array
    {
        try {

            // Использование "user данных" в ключе кэша
            $cacheKey = $this->getCacheKey([
                'methodName' => 'method', 'userId' => 1, 'ip' => '127.0.0.1'
            ]);

            $cacheItem = $this->cache->getItem($cacheKey);
            if ($cacheItem->isHit()) {
                return $cacheItem->get();
            }

            $result = parent::get($input);

            // Контроль времени кэширования
            $cacheItem
                ->set($result)
                ->expiresAt($this->getCacheDuration());

            return $result;
        } catch (Exception $e) {
            // Информативное логирование ошибок
            $this->logger->critical('Error while get response', [
                'code' => $e->getCode(),
                'message' => $e->getMessage(),
                'line' => $e->getLine(),
            ]);
        }

        return [];
    }

    /**
     * @param array $userData
     * @return string
     */
    public function getCacheKey(array $userData): string
    {
        return  md5('limiter:'.$userData['methodName'].''.$userData['userId'].''.$userData['ip']);
    }

    /**
     * @param int $duration
     * @throws \Exception
     */
    public function setCacheDuration(int $duration): void
    {
        $this->duration = (new DateTime())->modify('+'.$duration .' second"');
    }

    /**
     * На случай, если нам потребуется сбросить кэш.
     */
    public function clearCache(): bool
    {
        $cacheKey = $this->getCacheKey([
            'methodName' => 'method', 'userId' => 1, 'ip' => '127.0.0.1'
        ]);

        $cacheItem = $this->cache->getItem($cacheKey);
        $cacheItem->set('')->expiresAt(0);

        return true;
    }

    /**
     * @return \DateTime
     */
    public function getCacheDuration(): DateTime
    {
        return $this->duration;
    }
}