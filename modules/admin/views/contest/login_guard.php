<?php

use app\models\ExamLoginGuard;
use yii\grid\GridView;
use yii\helpers\Html;

/* @var $this yii\web\View */
/* @var $model app\models\Contest */
/* @var $dataProvider yii\data\ActiveDataProvider */
/* @var $username string */
/* @var $isGuardEnabled boolean */
/* @var $isGuardRunning boolean */
/* @var $currentExamContestId integer */

$this->title = $model->title . ' - 登录管控';
?>

<div>
    <p class="lead">登录管控 <?= Html::encode($model->title) ?></p>

    <?php if ($isGuardEnabled) : ?>
        <div class="alert <?= $isGuardRunning ? 'alert-success' : 'alert-warning' ?>">
            <i class="fas fa-fw fa-shield-alt"></i>
            本场考试登录管控已开启。
            <?php if ($isGuardRunning) : ?>
                当前比赛正在进行，登录限制已生效。
            <?php else : ?>
                当前比赛未处于进行中，登录限制会在比赛开始后生效。
            <?php endif; ?>
            服务端当前识别 IP：<?= Html::encode(ExamLoginGuard::getClientIp()) ?>。
        </div>
        <?= Html::beginForm(['disable-login-guard', 'id' => $model->id], 'post') ?>
        <?= Html::submitButton('关闭本场登录管控', [
            'class' => 'btn btn-outline-danger',
            'data' => [
                'confirm' => '确认关闭本场考试登录管控？',
            ],
        ]) ?>
        <?= Html::endForm() ?>
    <?php else : ?>
        <div class="alert alert-warning">
            <i class="fas fa-fw fa-exclamation-triangle"></i>
            本场考试登录管控未开启。仅查看本页面不会阻止学生登录。
            <?php if ($currentExamContestId > 0) : ?>
                当前全局单场比赛 ID 为 <?= Html::encode($currentExamContestId) ?>。
            <?php endif; ?>
            服务端当前识别 IP：<?= Html::encode(ExamLoginGuard::getClientIp()) ?>。
        </div>
        <?= Html::beginForm(['enable-login-guard', 'id' => $model->id], 'post') ?>
        <?= Html::submitButton('启用本场登录管控', [
            'class' => 'btn btn-warning',
            'data' => [
                'confirm' => '确认启用本场考试登录管控？启用后非机房 IP、再次登录、IP 变更都会等待管理员批准。',
            ],
        ]) ?>
        <?= Html::endForm() ?>
    <?php endif; ?>

    <p></p>

    <div class="alert alert-light">
        <i class="fas fa-fw fa-info-circle"></i>
        考试模式下，参赛用户首次登录必须来自 <?= Html::encode(ExamLoginGuard::getLabCidr()) ?>。非机房网段、退出后再次登录、或 IP 变更都会被阻止并等待管理员批准。
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
                'label' => '处理记录',
                'format' => 'raw',
                'value' => function ($guard) {
                    if (empty($guard->handled_at)) {
                        return '<span class="text-muted">无</span>';
                    }
                    $handler = $guard->handler === null
                        ? $guard->handled_by
                        : $guard->handler->username;
                    $note = empty($guard->admin_note)
                        ? '<span class="text-muted">无备注</span>'
                        : Html::encode($guard->admin_note);
                    return Html::encode($handler)
                        . '<br><span class="text-muted">' . Html::encode($guard->handled_at) . '</span>'
                        . '<br>' . $note;
                },
                'headerOptions' => ['style' => 'min-width:180px;']
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
                    $forms = [];
                    if (!empty($guard->last_request_ip)) {
                        $forms[] = Html::beginForm(['approve-login', 'id' => $model->id, 'guardId' => $guard->id], 'post', ['class' => 'form-inline mb-1'])
                            . Html::textInput('admin_note', '', [
                                'class' => 'form-control form-control-sm mr-1',
                                'placeholder' => '备注',
                                'style' => 'max-width:120px;'
                            ])
                            . Html::submitButton('批准一次', [
                                'class' => 'btn btn-sm btn-outline-success',
                                'data' => [
                                    'confirm' => '确认批准该用户从当前申请 IP 登录一次？',
                                ],
                            ])
                            . Html::endForm();
                    }
                    $forms[] = Html::beginForm(['reject-login', 'id' => $model->id, 'guardId' => $guard->id], 'post', ['class' => 'form-inline'])
                        . Html::textInput('admin_note', '', [
                            'class' => 'form-control form-control-sm mr-1',
                            'placeholder' => '备注',
                            'style' => 'max-width:120px;'
                        ])
                        . Html::submitButton('拒绝', [
                            'class' => 'btn btn-sm btn-outline-danger',
                            'data' => [
                                'confirm' => '确认拒绝该登录申请？',
                            ],
                        ])
                        . Html::endForm();
                    return implode('', $forms);
                },
                'headerOptions' => ['style' => 'min-width:230px;']
            ],
        ],
    ]) ?>
</div>
