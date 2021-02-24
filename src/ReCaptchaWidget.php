<?php

declare(strict_types=1);

namespace manchenkov\yii\recaptcha;

use Yii;
use yii\base\InvalidConfigException;
use yii\helpers\Html;
use yii\web\View;
use yii\widgets\InputWidget;

/**
 * ReCaptchaWidget class to render hidden input for Google reCAPTCHA v3
 * @package Manchenkov\Yii\Recaptcha
 */
class ReCaptchaWidget extends InputWidget
{
    /**
     * Secret key attribute name in Yii params file
     */
    private const SITE_API_CONFIG_KEY = 'reCAPTCHA.siteKey';

    /**
     * String format of required Google JS script, 'siteKey' must be set here
     */
    private const VENDOR_JS_FILEPATH_FORMAT = 'https://www.google.com/recaptcha/api.js?render=%s';

    /**
     * reCaptcha action name
     * @var string
     */
    public string $action = 'homepage';

    /**
     * Enables visibility of Google Privacy badge
     * @var bool
     */
    public bool $showBadge = true;

    /**
     * Enables preloading reCaptcha token on form init
     * @var bool
     */
    public bool $preloading = false;

    /**
     * Google API key
     * @var string
     */
    private string $apiKey;

    /**
     * Checks necessary configuration for initialization
     * @throws InvalidConfigException
     */
    public function init()
    {
        if (!array_key_exists(self::SITE_API_CONFIG_KEY, Yii::$app->params)) {
            throw new InvalidConfigException('Google reCAPTCHA site key must be specified!');
        }

        $this->apiKey = Yii::$app->params[self::SITE_API_CONFIG_KEY];

        // checks that model contain selected attribute
        if (!$this->model->hasProperty($this->attribute)) {
            throw new InvalidConfigException('Invalid model reCAPTCHA attribute name');
        }

        parent::init();
    }

    /**
     * Renders hidden input and registers JS scripts
     * @return string
     * @throws InvalidConfigException
     */
    public function run(): string
    {
        $this->enableScripts();
        $this->setUpActiveField();
        $this->enableReCaptchaCallbacks();

        return Html::activeHiddenInput($this->model, $this->attribute, ['value' => '']);
    }

    /**
     * @throws InvalidConfigException
     */
    private function enableScripts(): void
    {
        $remoteJsFile = sprintf(self::VENDOR_JS_FILEPATH_FORMAT, $this->apiKey);
        $uniqueJsKey = sprintf('recaptcha-js-%s', $this->apiKey);

        // include external Google API script
        $this->view->registerJsFile($remoteJsFile, ['position' => View::POS_HEAD], $uniqueJsKey);

        // hide Google badge (https://developers.google.com/recaptcha/docs/faq)
        if (!$this->showBadge) {
            $this->view->registerCss('.grecaptcha-badge {visibility: hidden;}');
        }
    }

    private function setUpActiveField(): void
    {
        if (!$this->field) {
            return;
        }

        if (!$this->showBadge) {
            // setup Google's hint if badge was hidden
            $this->field->hint(
                "This site is protected by reCAPTCHA and "
                . "the Google <a href='https://policies.google.com/privacy'>Privacy Policy</a> "
                . "and <a href='https://policies.google.com/terms'>Terms of Service</a> apply."
            );
        }

        $this->field->label(false);
    }

    private function enableReCaptchaCallbacks(): void
    {
        $reCaptchaFieldId = Html::getInputId($this->model, $this->attribute);

        $jsCallbackScript = $this->preloading
            ? $this->getScriptWithPreloading($reCaptchaFieldId)
            : $this->getScriptOnSubmit($reCaptchaFieldId);

        $this->view->registerJs($jsCallbackScript, View::POS_END);
    }

    private function getScriptWithPreloading(string $fieldId): string
    {
        return <<<JS
let reCaptchaTaskID = undefined;

function refreshCaptchaToken(formField) {
    grecaptcha
        .execute('{$this->apiKey}', {action: '{$this->action}'})
        .then(
            function (token) {
                formField.value = token;
                console.debug('reCaptcha token was set');
            }
        );
    
    if (!reCaptchaTaskID) {
        reCaptchaTaskID = setInterval(
            function () {
                refreshCaptchaToken(formField);
            }, 
            1000 * 60 * 2
        );
    }
}

grecaptcha.ready(function() {
    let form = document.querySelector('#{$fieldId}').closest('form');
    let reCaptchaField = document.querySelector('#{$fieldId}');
    
    refreshCaptchaToken(reCaptchaField);
    
    form.onsubmit = (e) => {
        refreshCaptchaToken(reCaptchaField);
    };
});
JS;
    }

    private function getScriptOnSubmit(string $fieldId): string
    {
        return <<<JS
grecaptcha.ready(function() {
    let form = document.querySelector('#{$fieldId}').closest('form');
    let reCaptchaField = document.querySelector('#{$fieldId}');
    
    form.onsubmit = (e) => {
        e.preventDefault();
        
        grecaptcha
            .execute('{$this->apiKey}', {action: '{$this->action}'})
            .then(function(token) {
                reCaptchaField.value = token;
                form.submit();
            });
    };
});
JS;
    }
}