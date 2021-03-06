<?php
/**
 * a model class representing comments crud
 * This is the DbTable class for the comments table.
 */

class Model_Comment  {

        /** Model_Table_Comment */
	protected $_table;
	
	/**
	 * Retrieve table object
	 * @return Model_Comment_Table
	 * */
	public function getTable() {
		if (null === $this->_table) {
			
			require_once APPLICATION_PATH . '/models/DbTable/Comment.php';
			$this->_table = new Model_DbTable_Comment ( );
		}
		return $this->_table;
	}
        
        
        /**
	 * Save a new entry
	 * * @param  array $data
	 * * @return int|string
	 * */
	public function save(array $data) {
		$table = $this->getTable ();
		$fields = $table->info ( Zend_Db_Table_Abstract::COLS );
		foreach ( $data as $field => $value ) {
			if (! in_array ( $field, $fields )) {
				unset ( $data [$field] );
			}
		}
		return $table->insert ( $data );
	}
	
	public function update(array $data) {
		$table = $this->getTable ();
		$where = $table->getAdapter ()->quoteInto ( 'id= ?', $data ['id'] );
		$table->update ( $data, $where );
	
	}


         public function countCommentsAd($id){

                if ($id){

                    $table = $this->getTable ();

                    $select = $table->select();
                    $select->from($table, 'id');
                    $select->where('ads_id = ?', (int)$id);
                    $result = count($table->fetchAll ( $select ));

                    }

		return  $result;

        }



        public function updateCommentsAd( $id,$count){
                $id = (int) $id;

                $table = new Zend_Db_Table('commentsAdCount');
                $sql = "INSERT INTO commentsAdCount   ( id_comment, count ) VALUES  ( ?,? )
                            ON DUPLICATE KEY UPDATE id_comment = ?, count = ?";

                $values = array("id_comment"=>$id, "count"=>$count);
                $result = $table->getAdapter()->query($sql, array_merge(array_values($values), array_values($values)));

    }

}