<?php

namespace App;

use Illuminate\Auth\Authenticatable;
use Illuminate\Auth\Passwords\CanResetPassword;
use Illuminate\Contracts\Auth\Authenticatable as AuthenticatableContract;
use Illuminate\Contracts\Auth\CanResetPassword as CanResetPasswordContract;
use Illuminate\Database\Eloquent\Model;
//use Illuminate\Database\Eloquent\SoftDeletes;
use Laravel\Socialite\Contracts\User as SocialUser;

class User extends Model implements AuthenticatableContract, CanResetPasswordContract
{
    use Authenticatable, CanResetPassword;

    /**
     * The database table used by the model.
     *
     * @var string
     */
    protected $table = 'users';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = ['username', 'email', 'password'];

    /**
     * The attributes excluded from the model's JSON form.
     *
     * @var array
     */
    //protected $hidden = ['password', 'remember_token'];
    
     /* The attributes that are not mass-assignable.
     *
     * @var array
     */
    //protected $guarded = ['id', 'name', 'created_at', 'updated_at'];

    /**
     * The attributes that are not visible.
     *
     * @var array
     */
    //protected $hidden = ['email'];

    /**
     * What should be returned when this model is converted to string.
     *
     * @return string
     */
    public function __toString()
    {
        if($this->nickname)
            return (string) $this->nickname;

        return (string) ($this->name) ?: $this->uuid;
    }

    /**
     * Get the human-friendly singular name of the resource.
     *
     * @return string
     */
    protected function getSingularAttribute()
    {
        return _('User');
    }

    /**
     * Get the human-friendly plural name of the resource.
     *
     * @return string
     */
    protected function getPluralAttribute()
    {
        return _('Users');
    }

    // Relationships ===============================================================

    public function language()
    {
        return $this->belongsTo('App\Language');
    }

    public function provider()
    {
        return $this->belongsTo('App\Provider');
    }

    public function role()
    {
        return $this->belongsTo('App\Role');
    }

    public function versions()
    {
        return $this->hasMany('App\Version');
    }
    
    // Events ======================================================================

    // Static Methods ==============================================================

    /**
     * Create new user if it does not exists.
     *
     * @param  Provider
     * @param  \Laravel\Socialite\Contracts\User
     * @return User
     */
    public static function findOrCreate(Provider $provider, SocialUser $socialUser)
    {
            // If user already exists reuse it
            $user = self::where([
                    'uuid' => $socialUser->getId(),
                    'provider_id' => $provider->id
            ])->withTrashed()->first();

            if($user)
                    return $user;

            // Create a new user
            $user = new static;
            $user->uuid = $socialUser->getId();
            $user->name = $socialUser->getName();
            $user->nickname = $socialUser->getNickname();
            $user->email = $socialUser->getEmail();
            $user->avatar = $socialUser->getAvatar();
            $user->provider_id = $provider->id;
            $user->language_id = app('language')->id;
            $user->role_id = Role::whereIsDefault(true)->firstOrFail()->id;
            $user->save();

            return $user;
    }

    // Bussiness logic =============================================================

    /**
     * Set the token value for the "remember me" session.
     *
     * @param  string  $value
     * @return void
     */
    public function setRememberToken($value)
    {
        // Social login does not need this feature
    }

    /**
     * Determine if user's role is authorized to execute $action on $resource.
     *
     * @param  string $action
     * @param  string $resource
     * @return bool
     */
    public function can($action, $resource)
    {
        return $this->role->can($action, $resource);
    }

    public static function getUsers($id = 0)
    {
        $conn = ldap_connect(env('LDAP_SERVER'))or die("Couldn't connect to LDAP!");
        ldap_set_option($conn, LDAP_OPT_PROTOCOL_VERSION, 3);
        ldap_set_option($conn, LDAP_OPT_REFERRALS, 0);
        $bind = ldap_bind($conn, env('LDAP_ADMIN'), env('LDAP_ADMIN_PASS'));

        if ($id) { // get specific user

        } else { // get all users
            $filter_uid = "(uid=*)";
            $result_uid = ldap_search($conn, env('LDAP_BASE_DN'), $filter_uid, array("mail", "userPassword","entryUUID", "name"));
            $entries_uid = ldap_get_entries($conn, $result_uid);
            return json_encode($entries_uid);
        }
    }

    public static function deleteAdmin($user_cn = 0)
    {

        $conn = ldap_connect(env('LDAP_SERVER'))or die("Couldn't connect to LDAP!");
        ldap_set_option($conn, LDAP_OPT_PROTOCOL_VERSION, 3);
        ldap_set_option($conn, LDAP_OPT_REFERRALS, 0);
        $bind = ldap_bind($conn, env('LDAP_ADMIN'), env('LDAP_ADMIN_PASS'));

        //delete entry from directory
        $dn="cn=".$user_cn.", ".env('LDAP_BASE_DN');
        if(@ldap_delete($conn, $dn))
        {
            DB::table('user_clients')->where('user_id', '=', $user_cn)->delete();

            return $user_cn." has been deleted";
        }
        else
        {
            return "Fail to deleted ".$user_cn;
        }

    }

    public static function updatePassword($data = array())
    {
        $password = $data['password'];

        $conn = ldap_connect(env('LDAP_SERVER'))or die("Couldn't connect to LDAP!");
        ldap_set_option($conn, LDAP_OPT_PROTOCOL_VERSION, 3);
        ldap_set_option($conn, LDAP_OPT_REFERRALS, 0);
        $bind = ldap_bind($conn, env('LDAP_ADMIN'), env('LDAP_ADMIN_PASS'));

        $filter_name = "(cn=" . $data['user_cn'] . ")";
        $attr = array('email');
        $result_name = ldap_search($conn, env('LDAP_BASE_DN'), $filter_name, $attr);
        $entries_name = ldap_get_entries($conn, $result_name);

        $user = $entries_name[0];
        $dn = $user['dn'];
        $new = array();
        $new['cn'] = $data['user_cn'];
        $new['userPassword'] = '{MD5}' . base64_encode(pack('H*', md5($data['password'])));
        return ldap_modify($conn, $dn, $new);

    }

    public static function validateUser($data = array())
    {

        $conn = ldap_connect(env('LDAP_SERVER'))or die("Couldn't connect to LDAP!");
        ldap_set_option($conn, LDAP_OPT_PROTOCOL_VERSION, 3);
        ldap_set_option($conn, LDAP_OPT_REFERRALS, 0);

        $bind = ldap_bind($conn, env('LDAP_ADMIN'), env('LDAP_ADMIN_PASS'));

        $filter_mail = "(mail=" . $data['email'] . ")";
        $filter_name = "(cn=" . $data['email'] . ")";

        $attr = array('email');

        $result_mail = ldap_search($conn, env('LDAP_BASE_DN'), $filter_mail, $attr);
        $entries_mail = ldap_get_entries($conn, $result_mail);
        echo print_r($entries_mail, true);
        
        $result_name = ldap_search($conn, env('LDAP_BASE_DN'), $filter_name, $attr);
        $entries_name = ldap_get_entries($conn, $result_name);
        
        echo print_r($entries_name, true);

        if ($entries_name['count'] == 0 && $entries_mail['count'] == 0) {
            $new_user["cn"] = $data['email'];
            //$new_user["displayName"] = $data['firstname'].' '.$data['lastname'];
            $new_user["mail"] = $data['email'];
            $new_user['objectclass'][0] = "inetOrgPerson";
            $new_user['objectclass'][1] = "top";

            $new_user["employeeType"] = 'Administrator';
            $new_user["givenName"] = $data['firstname'];
            $new_user['userPassword'] = '{MD5}' . base64_encode(pack('H*', md5($data['password'])));
            $new_user["sn"] = $data['lastname'];
            $new_user["uid"] = $data['email'];

            ldap_add($conn, 'cn=' . $data['email'] . ',' . env('LDAP_BASE_DN'), $new_user);

            Return "user has been added";
        } else{        
            Return "user email/username already exists";
        }

        ldap_unbind($conn);
    }
    
    public static function validateAuth($data) {
        $ldap = ldap_connect(env('LDAP_SERVER'));
        
        $ldaprdn = 'cn=' . $data['username'] . ','.env('LDAP_BASE_DN');
        //echo $ldaprdn;
        ldap_set_option($ldap, LDAP_OPT_PROTOCOL_VERSION, 3);
        ldap_set_option($ldap, LDAP_OPT_REFERRALS, 0);

        $bind = ldap_bind($ldap, $ldaprdn, $data['password']);


        if ($bind) {
            $filter="(cn={$data['username']})";
            $result = ldap_search($ldap,env('LDAP_BASE_DN'),$filter);
            ldap_sort($ldap,$result,"sn");
            $info = ldap_get_entries($ldap, $result);            
            ldap_close($ldap);

            //TODO!!!
            $user = self::where([
                    'username' => $info[0]['cn'][0],
                    //'password' => bcrypt($data['password'])
            ])->first();

            if($user)
                    return $user;
            
            // Create a new user
            $user = new static;
            $user->username = $info[0]['cn'][0];
            $user->name = $info[0]['givenname'][0] . ' ' . $info[0]['sn'][0];
            $user->email = $info[0]['mail'][0];
            $user->password = bcrypt($data['password']);
            $user->nickname = $info[0]['givenname'][0];
            $user->avatar = '';
            $user->uuid = $info[0]['cn'][0];
            $user->provider_id = 5;
            $user->role_id = 1;
            $user->language_id = 1;
            $user->created_at = date('Y-m-d H:i:s');
            $user->updated_at = date('Y-m-d H:i:s');

            $user->save();
            
            return $user;
        } else {
            return 'Cannot connect to LDAP server';
        }
    }
}
