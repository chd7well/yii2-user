<?php

/*
 * This file is part of the Dektrium project.
 *
 * (c) Dektrium project <http://github.com/dektrium/>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace 7well\user;

use yii\authclient\Collection;
use yii\base\BootstrapInterface;
use yii\base\InvalidConfigException;
use yii\i18n\PhpMessageSource;
use yii\web\GroupUrlRule;
use yii\console\Application as ConsoleApplication;
use yii\web\User;

/**
 * Bootstrap class registers module and user application component. It also creates some url rules which will be applied
 * when UrlManager.enablePrettyUrl is enabled.
 *
 * @author Dmitry Erofeev <dmeroff@gmail.com>
 */
class Bootstrap implements BootstrapInterface
{
    /** @var array Model's map */
    private $_modelMap = [
        'User'             => '7well\user\models\User',
        'Account'          => '7well\user\models\Account',
        'Profile'          => '7well\user\models\Profile',
        'Token'            => '7well\user\models\Token',
        'RegistrationForm' => '7well\user\models\RegistrationForm',
        'ResendForm'       => '7well\user\models\ResendForm',
        'LoginForm'        => '7well\user\models\LoginForm',
        'SettingsForm'     => '7well\user\models\SettingsForm',
        'RecoveryForm'     => '7well\user\models\RecoveryForm',
        'UserSearch'       => '7well\user\models\UserSearch',
    ];

    /** @inheritdoc */
    public function bootstrap($app)
    {
        /** @var $module Module */
        if ($app->hasModule('user') && ($module = $app->getModule('user')) instanceof Module) {
            $this->_modelMap = array_merge($this->_modelMap, $module->modelMap);
            foreach ($this->_modelMap as $name => $definition) {
                $class = "7well\\user\\models\\" . $name;
                \Yii::$container->set($class, $definition);
                $modelName = is_array($definition) ? $definition['class'] : $definition;
                $module->modelMap[$name] = $modelName;
                if (in_array($name, ['User', 'Profile', 'Token', 'Account'])) {
                    \Yii::$container->set($name . 'Query', function () use ($modelName) {
                        return $modelName::find();
                    });
                }
            }
            \Yii::$container->setSingleton(Finder::className(), [
                'userQuery'    => \Yii::$container->get('UserQuery'),
                'profileQuery' => \Yii::$container->get('ProfileQuery'),
                'tokenQuery'   => \Yii::$container->get('TokenQuery'),
                'accountQuery' => \Yii::$container->get('AccountQuery'),
            ]);

            if ($app instanceof ConsoleApplication) {
                $module->controllerNamespace = '7well\user\commands';
            } else {
                \Yii::$container->set('yii\web\User', [
                    'enableAutoLogin' => true,
                    'loginUrl'        => ['/user/security/login'],
                    'identityClass'   => $module->modelMap['User'],
                ]);

                $configUrlRule = [
                    'prefix' => $module->urlPrefix,
                    'rules'  => $module->urlRules
                ];

                if ($module->urlPrefix != 'user') {
                    $configUrlRule['routePrefix'] = 'user';
                }

                $app->get('urlManager')->rules[] = new GroupUrlRule($configUrlRule);

                if (!$app->has('authClientCollection')) {
                    $app->set('authClientCollection', [
                        'class' => Collection::className(),
                    ]);
                }
            }

            $app->get('i18n')->translations['user*'] = [
                'class'    => PhpMessageSource::className(),
                'basePath' => __DIR__ . '/messages',
            ];

            $defaults = [
                'welcomeSubject'        => \Yii::t('user', 'Welcome to {0}', \Yii::$app->name),
                'confirmationSubject'   => \Yii::t('user', 'Confirm account on {0}', \Yii::$app->name),
                'reconfirmationSubject' => \Yii::t('user', 'Confirm email change on {0}', \Yii::$app->name),
                'recoverySubject'       => \Yii::t('user', 'Complete password reset on {0}', \Yii::$app->name)
            ];

            \Yii::$container->set('7well\user\Mailer', array_merge($defaults, $module->mailer));
        }
        
    }
}