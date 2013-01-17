<?php

class rackspaceCloudFiles implements xrowCDNInterface
{
    private $vars = array();
    private $ini;
    private $auth;
    private $conn;
    private $container;
    private $object;

    /**
     * Constructor
     */
    function __construct( $ini )
    {
        $key = $ini->variable( 'Settings', 'APIKey' );
        $username = $ini->variable( 'Settings', 'Username' );
        $this->vars = array( 
            "api_key" => $key , 
            "username" => $username 
        );
        $this->ini = $ini;
        /* try force loading of CloudFiles API library */
        if ( class_exists( 'CF_Authentication' ) )
        {
            $this->auth = new CF_Authentication($username, $key);
			$this->auth->authenticate();
			$this->conn = new CF_Connection($this->auth);
        }
    
    }

    /**
     * Gets all files stored in the namespace / bucket in an array.
     *
     * @param $namespace Defines the name of the namespace / bucket.
     * @param $path Defines the pseudo-directory path to the files 
     * @throws Exception When an error occured
     */
    function getAllDistributedFiles( $namespace, $path = NULL )
    {
    	$this->container = $this->conn->get_container( $namespace );
    	$this->objects = $this->container->get_objects(0, NULL, NULL, $path);
    	return $this->objects;
    }

    /**
     * Clears all files out of the namespace / bucket
     *
     * @param $namespace Defines the name of the namespace / bucket.
     * @throws Exception When an error occured
     */
    function clean( $bucketName )
    {
    	if( $bucketName )
    	{
    		$this->container = $this->conn->get_container( $bucketName );
            $this->container->purge_from_cdn();
    	}
    }

    /**
     * Uploads a file into the bucket
     *
     * @param $bucket Defines the name of the namespace / bucket.
     * @param $file Defines the file (full path) to put into the namespace / bucket.
     * @param $remotepath Defines the remote location in the bucket / namespace to put the file into (without leading bucket).
     * @throws Exception When an error occured
     */
    function put( $localfile, $remotepath, $bucket )
    {
    		$this->container = $this->conn->get_container( $bucket );
    		$this->object = $this->container->create_object($remotepath);

    		$mime_types = array(
    		    'css' => 'text/css',
    		    'js'  => 'application/javascript'
    		);
    		$ext = strtolower( array_pop( explode( '.', $localfile ) ) );
    		if( isset( $mime_types[ $ext ] ) ) {
    		    $this->object->content_type = $mime_types[ $ext ];
    		}
    		$this->object->load_from_filename($localfile);
    }

}

?>