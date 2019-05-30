<?php
/**
 * Created by Artyom Manchenkov
 * artyom@manchenkoff.me
 * manchenkoff.me © 2019
 */

namespace Manchenkov\Yii\Recaptcha;

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
     * reCaptcha action name
     * @var string
     */
    public $action;

    /**
     * Enables visibility of Google Privacy badge
     * @var bool
     */
    public $showBadge = true;

    /**
     * Google API key
     * @var string
     */
    private $apiKey;

    /**
     * Checks necessary configuration for initialization
     * @throws InvalidConfigException
     */
    public function init()
    {
        // checks that application params contain API_KEY
        if (!isset(Yii::$app->params['reCAPTCHA.siteKey'])) {
            throw new InvalidConfigException('Google reCAPTCHA site key must be specified!');
        } else {
            $this->apiKey = Yii::$app->params['reCAPTCHA.siteKey'];
        }

        // checks that model contain selected attribute
        if (!$this->model->hasProperty($this->attribute)) {
            throw new InvalidConfigException('Invalid model reCAPTCHA attribute name');
        }

        parent::init();
    }

    /**
     * Renders hidden input and registers JS scripts
     * @return string
     */
    public function run()
    {
        // include external Google API script
        $this->view->registerJsFile(
            "https://www.google.com/recaptcha/api.js?render={$this->apiKey}",
            ['position' => View::POS_HEAD],
            'recaptcha-js-file'
        );

        // hide Google badge (https://developers.google.com/recaptcha/docs/faq)
        if (!$this->showBadge) {
            $this->view->registerCss('.grecaptcha-badge {visibility: hidden;}');
        }

        // modify ActiveField object
        if ($this->field) {
            if (!$this->showBadge) {
                // setup Google's hint if badge was hidden
                $this->field->hint(
                    "This site is protected by reCAPTCHA and "
                    . "the Google <a href='https://policies.google.com/privacy'>Privacy Policy</a> "
                    . "and <a href='https://policies.google.com/terms'>Terms of Service</a> apply."
                );
            }

            // reset the label
            $this->field->label(false);
        }

        // generate input ID to change a value
        $id = Html::getInputId($this->model, $this->attribute);

        // execute captcha and remember result token into a hidden input
        $js = <<<JS
grecaptcha.ready(function() {
    let form = document.querySelector('#{$id}').closest('form');
    
    form.onsubmit = (e) => {
        e.preventDefault();
        
        grecaptcha
            .execute('{$this->apiKey}', {action: '{$this->action}'})
            .then(function(token) {
                document.querySelector('#{$id}').value = token;
                form.submit();
            });
    };
});
JS;

        // append script to the view
        $this->view->registerJs($js, View::POS_END);

        // return a hidden input HTML content
        return Html::activeHiddenInput($this->model, $this->attribute, ['value' => '']);
    }
}