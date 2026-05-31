<?php

namespace app\models;

use Yii;
use yii\db\Expression;
use yii\db\IntegrityException;

/**
 * ExamLoginGuard records and enforces contest-mode login restrictions.
 *
 * @property integer $id
 * @property integer $contest_id
 * @property integer $user_id
 * @property string $active_session_id
 * @property string $first_login_at
 * @property string $first_login_ip
 * @property string $first_login_user_agent
 * @property string $last_login_at
 * @property string $last_login_ip
 * @property string $last_login_user_agent
 * @property string $last_request_at
 * @property string $last_request_ip
 * @property string $last_request_user_agent
 * @property string $approved_ip
 * @property integer $approved_login_count
 * @property integer $used_login_count
 * @property integer $status
 * @property integer $handled_by
 * @property string $handled_at
 * @property string $admin_note
 * @property string $created_at
 * @property string $updated_at
 */
class ExamLoginGuard extends ActiveRecord
{
    const LAB_CIDR = '10.191.0.0/16';
    const ACTIVE_CONTEST_CACHE_DURATION = 10;

    const STATUS_ALLOWED = 1;
    const STATUS_PENDING = 2;
    const STATUS_APPROVED = 3;
    const STATUS_USED = 4;
    const STATUS_REJECTED = 5;

    private static $_activeContests = [];

    public static function tableName()
    {
        return '{{%exam_login_guard}}';
    }

    public function behaviors()
    {
        return [
            'timestamp' => $this->timeStampBehavior(),
        ];
    }

    public function rules()
    {
        return [
            [['contest_id', 'user_id'], 'required'],
            [['contest_id', 'user_id', 'approved_login_count', 'used_login_count', 'status', 'handled_by'], 'integer'],
            [['first_login_at', 'last_login_at', 'last_request_at', 'handled_at', 'created_at', 'updated_at'], 'safe'],
            [['active_session_id'], 'string', 'max' => 128],
            [['first_login_ip', 'last_login_ip', 'last_request_ip', 'approved_ip'], 'string', 'max' => 45],
            [['first_login_user_agent', 'last_login_user_agent', 'last_request_user_agent'], 'string', 'max' => 512],
            [['admin_note'], 'string', 'max' => 255],
            [['contest_id', 'user_id'], 'unique', 'targetAttribute' => ['contest_id', 'user_id']],
        ];
    }

    public function attributeLabels()
    {
        return [
            'id' => 'ID',
            'contest_id' => 'Contest ID',
            'user_id' => 'User ID',
            'active_session_id' => 'Active Session',
            'first_login_at' => '首次登录时间',
            'first_login_ip' => '首次登录 IP',
            'first_login_user_agent' => '首次登录 UA',
            'last_login_at' => '最近登录时间',
            'last_login_ip' => '最近登录 IP',
            'last_login_user_agent' => '最近登录 UA',
            'last_request_at' => '最近申请时间',
            'last_request_ip' => '最近申请 IP',
            'last_request_user_agent' => '最近申请 UA',
            'approved_ip' => '批准 IP',
            'approved_login_count' => '批准次数',
            'used_login_count' => '已使用次数',
            'status' => '状态',
            'handled_by' => '处理人',
            'handled_at' => '处理时间',
            'admin_note' => '管理员备注',
            'created_at' => 'Created At',
            'updated_at' => 'Updated At',
        ];
    }

    public function getUser()
    {
        return $this->hasOne(User::class, ['id' => 'user_id']);
    }

    public function getContest()
    {
        return $this->hasOne(Contest::class, ['id' => 'contest_id']);
    }

    public function getHandler()
    {
        return $this->hasOne(User::class, ['id' => 'handled_by']);
    }

    public static function getActiveContestForUser(User $user)
    {
        $cacheKey = $user->id;
        if (array_key_exists($cacheKey, self::$_activeContests)) {
            return self::$_activeContests[$cacheKey];
        }

        if ($user->isAdmin()) {
            return self::$_activeContests[$cacheKey] = null;
        }

        if (!Yii::$app->setting->get('isContestMode')) {
            return self::$_activeContests[$cacheKey] = null;
        }

        $contestId = intval(Yii::$app->setting->get('examContestId'));
        if ($contestId <= 0) {
            return self::$_activeContests[$cacheKey] = null;
        }

        $contest = Yii::$app->db->cache(function () use ($contestId) {
            return Contest::findOne($contestId);
        }, self::ACTIVE_CONTEST_CACHE_DURATION);
        if ($contest === null || $contest->getRunStatus() != Contest::STATUS_RUNNING) {
            return self::$_activeContests[$cacheKey] = null;
        }

        $inContest = Yii::$app->db->cache(function () use ($contest, $user) {
            return ContestUser::find()
                ->where(['contest_id' => $contest->id, 'user_id' => $user->id])
                ->exists();
        }, self::ACTIVE_CONTEST_CACHE_DURATION);

        return self::$_activeContests[$cacheKey] = ($inContest ? $contest : null);
    }

    public static function isActiveForUser(User $user)
    {
        return self::getActiveContestForUser($user) !== null;
    }

    public static function checkLoginAllowed(User $user, &$message = null)
    {
        $contest = self::getActiveContestForUser($user);
        if ($contest === null) {
            return true;
        }

        $ip = self::getClientIp();
        $userAgent = self::getUserAgent();
        $guard = self::findOrCreate($contest->id, $user->id);

        if ($guard->active_session_id === Yii::$app->session->getId()) {
            return true;
        }

        if (empty($guard->first_login_at) && self::isLabIp($ip)) {
            return true;
        }

        if ($guard->hasAvailableApproval($ip)) {
            $guard->used_login_count++;
            $guard->status = $guard->used_login_count >= $guard->approved_login_count
                ? self::STATUS_USED
                : self::STATUS_APPROVED;
            $guard->save(false);
            return true;
        }

        $guard->last_request_at = new Expression('NOW()');
        $guard->last_request_ip = $ip;
        $guard->last_request_user_agent = $userAgent;
        $guard->status = self::STATUS_PENDING;
        $guard->save(false);

        $message = self::isLabIp($ip)
            ? '该账号已经在本场考试中登录过。再次登录需要等待管理员批准。'
            : '当前 IP 不在机房网段，登录已被阻止。请联系管理员批准本次登录。';

        return false;
    }

    public static function markLoginSuccess(User $user)
    {
        $contest = self::getActiveContestForUser($user);
        if ($contest === null) {
            return true;
        }

        $guard = self::findOrCreate($contest->id, $user->id);
        $ip = self::getClientIp();
        $userAgent = self::getUserAgent();
        $now = new Expression('NOW()');

        if (empty($guard->first_login_at)) {
            $guard->first_login_at = $now;
            $guard->first_login_ip = $ip;
            $guard->first_login_user_agent = $userAgent;
        }

        $guard->active_session_id = Yii::$app->session->getId();
        $guard->last_login_at = $now;
        $guard->last_login_ip = $ip;
        $guard->last_login_user_agent = $userAgent;
        if ($guard->status != self::STATUS_APPROVED && $guard->status != self::STATUS_USED) {
            $guard->status = self::STATUS_ALLOWED;
        }

        return $guard->save(false);
    }

    public static function ensureCurrentSession(User $user, &$message = null)
    {
        $contest = self::getActiveContestForUser($user);
        if ($contest === null) {
            return true;
        }

        $guard = self::findOne(['contest_id' => $contest->id, 'user_id' => $user->id]);
        if ($guard !== null && $guard->active_session_id === Yii::$app->session->getId()) {
            return true;
        }

        if (!self::checkLoginAllowed($user, $message)) {
            return false;
        }

        return self::markLoginSuccess($user);
    }

    public function approve($adminId, $note = '')
    {
        $ip = $this->last_request_ip;
        if (empty($ip)) {
            $ip = $this->last_login_ip ?: $this->first_login_ip;
        }

        $this->approved_ip = $ip;
        $this->approved_login_count++;
        $this->status = self::STATUS_APPROVED;
        $this->handled_by = $adminId;
        $this->handled_at = new Expression('NOW()');
        $this->admin_note = $note;

        return $this->save(false);
    }

    public function reject($adminId, $note = '')
    {
        $this->status = self::STATUS_REJECTED;
        $this->handled_by = $adminId;
        $this->handled_at = new Expression('NOW()');
        $this->admin_note = $note;

        return $this->save(false);
    }

    public function hasAvailableApproval($ip)
    {
        return !empty($this->approved_ip)
            && $this->approved_ip === $ip
            && $this->approved_login_count > $this->used_login_count;
    }

    public function getStatusText()
    {
        switch ($this->status) {
            case self::STATUS_ALLOWED:
                return '已准入';
            case self::STATUS_PENDING:
                return '待审批';
            case self::STATUS_APPROVED:
                return '已批准';
            case self::STATUS_USED:
                return '批准已使用';
            case self::STATUS_REJECTED:
                return '已拒绝';
            default:
                return '未知';
        }
    }

    public function getIsLastRequestInLab()
    {
        return self::isLabIp($this->last_request_ip ?: $this->last_login_ip);
    }

    public static function getClientIp()
    {
        return Yii::$app->request->userIP;
    }

    public static function getUserAgent()
    {
        return substr((string) Yii::$app->request->userAgent, 0, 512);
    }

    public static function isLabIp($ip)
    {
        return self::ipInCidr($ip, self::getLabCidr());
    }

    public static function getLabCidr()
    {
        return Yii::$app->params['examLabCidr'] ?? self::LAB_CIDR;
    }

    protected static function findOrCreate($contestId, $userId)
    {
        $guard = self::findOne(['contest_id' => $contestId, 'user_id' => $userId]);
        if ($guard !== null) {
            return $guard;
        }

        $guard = new self();
        $guard->contest_id = $contestId;
        $guard->user_id = $userId;
        $guard->status = self::STATUS_PENDING;
        $guard->approved_login_count = 0;
        $guard->used_login_count = 0;

        try {
            $guard->save(false);
        } catch (IntegrityException $e) {
            $guard = self::findOne(['contest_id' => $contestId, 'user_id' => $userId]);
            if ($guard !== null) {
                return $guard;
            }
            throw $e;
        }

        return $guard;
    }

    protected static function isValidIp($ip)
    {
        return filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== false;
    }

    protected static function ipInCidr($ip, $cidr)
    {
        if (!self::isValidIp($ip)) {
            return false;
        }

        $parts = explode('/', $cidr, 2);
        $subnet = $parts[0];
        $bits = $parts[1] ?? 32;
        $bits = intval($bits);
        if ($bits < 0 || $bits > 32 || !self::isValidIp($subnet)) {
            return false;
        }

        $ipLong = ip2long($ip);
        $subnetLong = ip2long($subnet);
        $mask = $bits === 0 ? 0 : (-1 << (32 - $bits));

        return ($ipLong & $mask) === ($subnetLong & $mask);
    }
}
