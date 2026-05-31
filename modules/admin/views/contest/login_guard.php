<?php

use app\models\ExamLoginGuard;
use yii\grid\GridView;
use yii\helpers\Html;

/* @var $this yii\web\View */
/* @var $model app\models\Contest */
/* @var $dataProvider yii\data\ActiveDataProvider */
/* @var $username string */

$this->title = $model->title . ' - 登录管控';
?>

<div>
    <p class="lead">登录管控 <?= Html::encode($model->title) ?></p>

    <div class="alert alert-light">
        <i class="fas fa-fw fa-info-circle"></i>
        考试模式下，参赛用户首次登录必须来自 <?= Html::encode(ExamLoginGuard::LAB_CIDR) ?>。非机房网段、退出后再次登录、或 IP 变更都会被阻止并等待管理员批准。
    </div>

    <?= Html::beginForm(['login-guard', 'id' => $model->id], 'get') ?>
    <div class="input-group">
        <?= Html::textInput('username', $username, ['class' => 'form-control', 'placeholder' => '用户名或昵称']) ?>
        <div class="input-group-append">
            <?= Html::submitButton('搜索', ['class' => 'btn btn-outline-primary']) ?>
            <?= Html::a('清空', ['login-guard', 'id' => $model->id], ['class' => 'btn btn-outline-secondary']) ?>
        </div>
    </div>
    <?= Html::endForm() ?>

    <p></p>

    <?= GridView::widget([
        'dataProvider' => $dataProvider,
        'layout' => '{items}{pager}',
        'options' => ['class' => 'table-responsive'],
        'tableOptions' => ['class' => 'table table-bordered'],
        'columns' => [
            [
                'label' => '用户',
                'format' => 'raw',
                'value' => function ($guard) {
                    if ($guard->user === null) {
                        return Html::encode($guard->user_id);
                    }
                    return Html::a(Html::encode($guard->user->username), ['/admin/user/view', 'id' => $guard->user_id])
                        . '<br><span class="text-muted">' . Html::encode($guard->user->nickname) . '</span>';
                },
                'headerOptions' => ['style' => 'min-width:120px;']
            ],
            [
                'label' => '状态',
                'format' => 'raw',
                'value' => function ($guard) {
                    $class = 'secondary';
                    if ($guard->status == ExamLoginGuard::STATUS_PENDING) {
                        $class = 'warning';
                    } else if ($guard->status == ExamLoginGuard::STATUS_APPROVED) {
                        $class = 'success';
                    } else if ($guard->status == ExamLoginGuard::STATUS_REJECTED) {
                        $class = 'danger';
                    } else if ($guard->status == ExamLoginGuard::STATUS_ALLOWED) {
                        $class = 'primary';
                    }
                    return '<span class="badge badge-' . $class . '">' . Html::encode($guard->getStatusText()) . '</span>';
                },
                'headerOptions' => ['style' => 'min-width:90px;']
            ],
            [
                'label' => '首次登录',
                'format' => 'raw',
                'value' => function ($guard) {
                    if (empty($guard->first_login_at)) {
                        return '<span class="text-muted">未成功登录</span>';
                    }
                    return Html::encode($guard->first_login_ip)
                        . '<br><span class="text-muted">' . Html::encode($guard->first_login_at) . '</span>';
                },
                'headerOptions' => ['style' => 'min-width:150px;']
            ],
            [
                'label' => '最近申请',
                'format' => 'raw',
                'value' => function ($guard) {
                    if (empty($guard->last_request_ip)) {
                        return '<span class="text-muted">无</span>';
                    }
                    $inLab = ExamLoginGuard::isLabIp($guard->last_request_ip);
                    $badge = $inLab
                        ? '<span class="badge badge-primary">机房</span>'
                        : '<span class="badge badge-danger">非机房</span>';
                    return Html::encode($guard->last_request_ip) . ' ' . $badge
                        . '<br><span class="text-muted">' . Html::encode($guard->last_request_at) . '</span>';
                },
                'headerOptions' => ['style' => 'min-width:170px;']
            ],
            [
                'label' => '批准/使用',
                'value' => function ($guard) {
                    return $guard->approved_login_count . ' / ' . $guard->used_login_count;
                },
                'headerOptions' => ['style' => 'min-width:90px;']
            ],
            [
                'label' => '批准 IP',
                'value' => function ($guard) {
                    return $guard->approved_ip ?: '-';
                },
                'headerOptions' => ['style' => 'min-width:130px;']
            ],
            [
                'label' => 'UA',
                'format' => 'raw',
                'value' => function ($guard) {
                    $ua = $guard->last_request_user_agent ?: $guard->last_login_user_agent;
                    if (empty($ua)) {
                        return '<span class="text-muted">无</span>';
                    }
                    return Html::tag('span', Html::encode(substr($ua, 0, 40)), [
                        'title' => $ua
                    ]);
                },
                'headerOptions' => ['style' => 'min-width:220px;']
            ],
            [
                'label' => '操作',
                'format' => 'raw',
                'value' => function ($guard) use ($model) {
                    $buttons = [];
                    if (!empty($guard->last_request_ip)) {
                        $buttons[] = Html::a('批准一次', ['approve-login', 'id' => $model->id, 'guardId' => $guard->id], [
                            'class' => 'btn btn-sm btn-outline-success',
                            'data' => [
                                'method' => 'post',
                                'confirm' => '确认批准该用户从当前申请 IP 登录一次？',
                            ],
                        ]);
                    }
                    $buttons[] = Html::a('拒绝', ['reject-login', 'id' => $model->id, 'guardId' => $guard->id], [
                        'class' => 'btn btn-sm btn-outline-danger',
                        'data' => [
                            'method' => 'post',
                            'confirm' => '确认拒绝该登录申请？',
                        ],
                    ]);
                    return implode(' ', $buttons);
                },
                'headerOptions' => ['style' => 'min-width:150px;']
            ],
        ],
    ]) ?>
</div>
