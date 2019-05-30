# Yii 2 reCAPTCHA v3

This package is a simple extension for Yii 2 which helps to work with [Google reCAPTCHA v3](https://developers.google.com/recaptcha/)

## Installation

You have to run following command to add a dependency to your project

```bash
composer require manchenkov/yii2-recaptcha3
```

or you can add this line to `require` section of `composer.json`

```
"manchenkov/yii2-recaptcha3": "*"
```

## Usage

In first you have to add following settings into your `params-local.php` file or your different preferred Git ignored configuration files

```
return [
    // your other params
    'reCAPTCHA.siteKey' => 'GOOGLE_RECAPTCHA_SITE_KEY',
    'reCAPTCHA.secretKey' => 'GOOGLE_RECAPTCHA_SECRET_KEY',
];
``` 

Then you should add the following rules to validate the model

```
public $captcha;

public function rules()
{
    return [
        // other validation rules
        ['captcha', ReCaptchaValidator::class, 'score' => 0.8, 'action' => 'login'],
    ];
}
```

- **score**: minimal value of acceptance score (default: **0.5**)
- **action**: page action name (default: **homepage**)
- **message**: error message to display (see **Localization** section below)

The last step is an adding ActiveForm input, just use the next example:

```
$form->field($model, 'captcha')->widget(ReCaptchaWidget::class);

// or

$form->field($model, 'captcha')->widget(ReCaptchaWidget::class, ['action' => 'login']);
// action name must be the same as validation rules
```

If You use Twig as a template engine, you can create helper function like this:

```
/**
/ $model: SomeForm model instance
/ $attribute: model property name
/ $action: captcha page action name
/ $showBadge: visibility of captcha badge on the page
**/
'reCaptcha' => function (Model $model, string $attribute, string $action = 'homepage', bool $showBadge = true) {
    return ReCaptchaWidget::widget([
        'model' => $model,
        'action' => $action,
        'attribute' => $attribute,
        'showBadge' => $showBadge
    ]);
};
```

and then use it in the **.twig** templates without ActiveForm

```twig
{{ reCaptcha(model, 'captcha', 'login') | raw }}
```

## Localization

If any error occurred during the validation, the default error message will be "Google reCAPTCHA verification failed". 
Input widget uses default [i18n localization](https://www.yiiframework.com/doc/guide/2.0/en/tutorial-i18n) to print it to the page, so you can add a translation for the next message:

```
// category - 'app'
Yii::t("app", "Google reCAPTCHA verification failed")

// For example: 'messages/en/app.php' (i18n php message sources)
"Google reCAPTCHA verification failed" => "Google reCAPTCHA verification failed",

// For example: 'messages/ru/app.php'
"Google reCAPTCHA verification failed" => "Не удалось проверить Google reCAPTCHA", 
``` 

## Hiding Google Privacy badge

By default, Google reCAPTCHA v3 print a badge in the right bottom corner of the page, but You can hide it by using the following example

```
$form->field($model, 'captcha')->widget(ReCaptchaWidget::class, [
    'action' => 'login',
    'showBadge' => false',
]);
```

In this case, input hint will contain default message recommended by Google, 
but you can change it by using of `field(...)->widget(...)->hint('Custom Google captcha message')`

Anyway, You have to include some mentioning text in your form, see details [here](https://developers.google.com/recaptcha/docs/faq)