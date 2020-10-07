<?php
/**
 * Created by Artyom Manchenkov
 * artyom@manchenkoff.me
 * manchenkoff.me © 2019
 */

declare(strict_types=1);

namespace manchenkov\yii\recaptcha;

use Yii;
use yii\base\InvalidConfigException;
use yii\httpclient\Client;
use yii\httpclient\CurlTransport;
use yii\httpclient\Exception;
use yii\validators\Validator;

class ReCaptchaValidator extends Validator
{
    /**
     * URL to verify response
     * @var string
     */
    private string $apiUrl = "https://www.google.com/recaptcha/api/";

    /**
     * Stop validation if an error occurred
     * @var bool
     */
    public $skipOnError = false;

    /**
     * Validation value is required
     * @var bool
     */
    public $skipOnEmpty = false;

    /**
     * Error if validation fails
     * @var string
     */
    public $message = "Google reCAPTCHA verification failed";

    /**
     * Acceptance score from Google API
     * @var float
     */
    public float $score = 0.5;

    /**
     * Action name to validate API response
     * @var string
     */
    public string $action = 'homepage';

    /**
     * Google reCAPTCHA v3 secret key
     * @var string
     */
    private string $secretKey;

    /**
     * Checks necessary configuration for initialization
     * @throws InvalidConfigException
     */
    public function init()
    {
        // checks that a site key exist in a config
        if (!isset(Yii::$app->params['reCAPTCHA.secretKey'])) {
            throw new InvalidConfigException('Google reCAPTCHA secret key must be specified!');
        } else {
            $this->secretKey = Yii::$app->params['reCAPTCHA.secretKey'];
        }

        parent::init();
    }

    /**
     * {@inheritdoc}
     * @throws Exception
     */
    public function validateAttribute($model, $attribute)
    {
        $token = $model->{$attribute};

        $currentHost = Yii::$app->request->hostName;

        $http = new Client(
            [
                'baseUrl' => $this->apiUrl,
                'transport' => CurlTransport::class,
            ]
        );

        $response = $http
            ->post(
                'siteverify',
                [
                    'secret' => $this->secretKey,
                    'response' => $token,
                    'remoteip' => Yii::$app->request->remoteIP,
                ]
            )
            ->send();

        if ($response->isOk) {
            $data = $response->data;

            if ($data['success'] && $data['hostname'] == $currentHost) {
                if ($data['action'] == $this->action && $data['score'] >= $this->score) {
                    return;
                }
            }
        }

        $this->addError(
            $model,
            $attribute,
            Yii::t('app', $this->message)
        );
    }
}