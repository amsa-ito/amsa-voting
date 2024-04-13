<?php


class Amsa_Voting_User{
    private $user_id;

    public function __construct($user_id){
        $this->user_id = $user_id;
    }

    public function get_single_meta($key){
        return get_user_meta($this->user_id, $key, true);
    }



    //returns array of user objects
    public function get_principals_of_user(){	
		return $this->get_single_meta('amsa_voting_principals');

	}

    public function nominate_proxy($proxy_id){
        update_user_meta($this->user_id, 'amsa_voting_proxy', $proxy_id);
        
		// update the principals behind the proxy, this should prevent the proxy nominating a proxy
        $principals=get_user_meta($proxy_id, 'amsa_voting_principals', true);
        if(!in_array($this->user_id, $principals)){
            $principals[]=$this->user_id;
            update_user_meta($proxy_id, 'amsa_voting_principals', $principals);
        }
    }

    public function retract_proxy(){
        $proxy_user_id = $this->get_single_meta('amsa_voting_proxy');

        // -1 means the default, 0 means been actively retracted
        if($proxy_user_id==0){
            return;
        }
        
        $principals=get_user_meta($proxy_user_id, 'amsa_voting_principals', true);
        if(($key = array_search($this->user_id, $principals))!==false){
            unset($principals[$key]);
            update_user_meta($proxy_user_id, 'amsa_voting_principals', $principals);

        }

        update_user_meta($this->user_id, 'amsa_voting_proxy', 0);

    }


}