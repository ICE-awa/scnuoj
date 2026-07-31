<?php

namespace app\components;

use app\models\ExamLoginGuard;
use Yii;
use yii\web\Controller;

/**
 * @author Shiyang <dr@shiyang.me>
 * @since 2.0
 */
class BaseController extends Controller
{

    public function init()
    {
        parent::init();
        $this->setLanguage();
    }

    public function beforeAction($action)
    {
        if (!parent::beforeAction($action)) {
            return false;
        }

        if (!Yii::$app->user->isGuest) {
            $message = null;
            if (!ExamLoginGuard::ensureCurrentSession(Yii::$app->user->identity, $message)) {
                Yii::$app->user->logout();
                Yii::$app->session->setFlash('error', $message);
                $this->redirect(['/site/login']);
                return false;
            }
        }

        return true;
    }

    /**
     * Set the language displayed on the current site
     */
    public function setLanguage()
    {
        Yii::$app->language = 'zh-CN';
    }
}
