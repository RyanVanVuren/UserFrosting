<?php

namespace UserFrosting;

class MySqlGroup extends MySqlDatabaseObject implements GroupObjectInterface {

    public function __construct($properties, $id = null) {
        $this->_table = static::getTable('group');
        parent::__construct($properties, $id);
    }
    
    // Return a collection of Users which belong to this group.
    public function getUsers(){
        //Get connected and load the group_user table
        $db = static::connection();
        $link_table = static::getTable('group_user')->name;

        $sqlVars[":id"] = $this->_id;

        $query = "
            SELECT user_id FROM `$link_table`
            WHERE group_id = :id";

        $stmt = $db->prepare($query);
        $stmt->execute($sqlVars);

        //Get the array of users in this group
        $users_array= [];
        while($user_id = $stmt->fetch(\PDO::FETCH_ASSOC)) {
            $users_array[] = UserLoader::fetch($user_id['user_id']);
        }

        return $users_array;
    }
    
    public function store(){        
        // If this is being set as the default primary group, then any other group must be demoted to default group
        if ($this->is_default == GROUP_DEFAULT_PRIMARY){
            $db = static::connection();
            
            $query = "
                UPDATE `{$this->_table->name}`
                SET is_default = " . GROUP_DEFAULT .
                " WHERE is_default = " . GROUP_DEFAULT_PRIMARY .
                ";";
            
            $stmt = $db->prepare($query);
            $stmt->execute();
        }
        
        // Now store this group
        parent::store();
        
        // Store function should always return the id of the object
        return $this->_id;
    }
    
    /*** Delete this group from the database, along with any linked user and authorization rules
    ***/
    public function delete(){        
        // Can only delete an object where `id` is set
        if (!$this->_id) {
            return false;
        }
        
        $result = parent::delete();
        
        // Get connection
        $db = static::connection();
        $link_table = static::getTable('group_user')->name;
        $auth_table = static::getTable('authorize_group')->name;
        
        $sqlVars[":id"] = $this->_id;
        
        $query = "
            DELETE FROM `$link_table`
            WHERE group_id = :id";
            
        $stmt = $db->prepare($query);
        $stmt->execute($sqlVars);
     
        $query = "
            DELETE FROM `$auth_table`
            WHERE group_id = :id";
            
        $stmt = $db->prepare($query);
        $stmt->execute($sqlVars);     

        // Reassign any primary users to the current default primary group
        $default_primary_group = GroupLoader::fetch(GROUP_DEFAULT_PRIMARY, 'is_default');
        
        $user_table = static::getTable('user')->name;
        
        $query = "
            UPDATE `$user_table` 
            SET primary_group_id = :primary_group_id
            WHERE primary_group_id = :current_id;";
        
        $sqlVars = [
            ":primary_group_id" => $default_primary_group->id,
            ":current_id"   => $this->_id
        ];
        
        $stmt = $db->prepare($query);
        $stmt->execute($sqlVars);
        
        // TODO: assign user to the default primary group as well?
        
        return $result;
    }
}

?>
