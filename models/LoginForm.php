<?php

namespace app\models;

use Yii;
use yii\base\Model;

/**
 * LoginForm is the model behind the login form.
 *
 * @property User|null $user This property is read-only.
 *
 */
class LoginForm extends Model
{
    public $username;
    public $password;
    public $rememberMe = true;
    public $verifyCode;

    private $_user = false;


    /**
     * @return array the validation rules.
     */
    public function rules()
    {
        return [
            // username and password are both required
            [['username', 'password'], 'required'],
            // rememberMe must be a boolean value
            ['rememberMe', 'boolean'],
            // password is validated by validatePassword()
            ['password', 'validatePassword'],
            ['verifyCode', 'captcha', 'on' => 'withCaptcha']
        ];
    }

    /**
     * @inheritdoc
     */
    public function attributeLabels()
    {
        return [
            'username' => Yii::t('app', 'Username Or Email'),
            'password' => Yii::t('app', 'Password'),
            'rememberMe' => Yii::t('app', 'Remember Me'),
            'verifyCode' => Yii::t('app', 'Verify Code')
        ];
    }

    /**
     * Validates the password.
     * This method serves as the inline validation for password.
     *
     * @param string $attribute the attribute currently being validated
     * @param array $params the additional name-value pairs given in the rule
     */
    public function validatePassword($attribute, $params)
    {
        if (!$this->hasErrors()) {
            $user = $this->getUser();
            if (!$user) {
                $this->addError('username', Yii::t('app', 'Username does not exist.'));
            } elseif (!$user->validatePassword($this->password)) {
                $this->addError('password', Yii::t('app', 'Incorrect password.'));
            }
        }
    }

    /**
     * Logs in a user using the provided username and password.
     * @return bool whether the user is logged in successfully
     */
    public function login()
    {
        if ($this->validate()) {
            $user = $this->getUser();
            $message = null;
            if (!ExamLoginGuard::checkLoginAllowed($user, $message)) {
                $this->addError('username', $message);
                return false;
            }

            $duration = ExamLoginGuard::isActiveForUser($user)
                ? 0
                : ($this->rememberMe ? 3600 * 24 * 30 : 0);

            if (Yii::$app->user->login($user, $duration)) {
                ExamLoginGuard::markLoginSuccess($user);
                return true;
            }
        }
        return false;
    }

    /**
     * Finds user by [[username]]
     *
     * @return User|null
     */
    public function getUser()
    {
        if ($this->_user === false) {
            $this->_user = User::findByLoginID($this->username);
        }

        return $this->_user;
    }
}
