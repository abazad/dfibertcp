<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

// This is AJAX after all...
header('Content-Type: application/json');

class Ajax extends CI_Controller {
	public function index()
	{
		if ( $this->session->userdata('uid') == FALSE ) {
			echo json_encode( array( 'result' => 'error', 'error' => 'Not logged in.' ) );
		} else {
			echo json_encode( array( 'result' => 'error', 'error' => 'No function specified.' ) );
		}
	}
	
	private function RPCInit() {
		$this->load->library( 'TransmissionRPC', null, 'TransmissionRPC' );
		$this->TransmissionRPC->url 				= 'http://' . $this->config->item('dft_host') . ':' . $this->config->item('dft_port') . '/transmission/rpc';
		$this->TransmissionRPC->username 			= $this->config->item('dft_username');
		$this->TransmissionRPC->password 			= $this->config->item('dft_password');
		$this->TransmissionRPC->return_as_array 	= TRUE;
		return $this->TransmissionRPC;
	}
	
	public function start( $id ) {
		if ( $this->session->userdata('uid') == FALSE ) {
			echo json_encode( array( 'result' => 'error', 'error' => 'Not logged in.' ) );
			exit;
		}
		$id = intval( $id );
		$this->RPCInit();
		$sql = 'SELECT `transmission_id` FROM dft_torrents WHERE `uid`=' . intval( $this->session->userdata('uid') ) . ' AND 
				`transmission_id` =' . $this->db->escape( $id ) . ';';
		$res = $this->db->query( $sql );
		if ( $res->num_rows > 0 ) {
			echo json_encode( $this->TransmissionRPC->start( $id ) );
		} else {
			echo json_encode( array( 'result' => 'error', 'error' => 'Not your torrent.' ) );
		}
		exit;
	}
	
	public function stop( $id ) {
		if ( $this->session->userdata('uid') == FALSE ) {
			echo json_encode( array( 'result' => 'error', 'error' => 'Not logged in.' ) );
			exit;
		}
		$id = intval( $id );
		$this->RPCInit();
		$sql = 'SELECT `transmission_id` FROM dft_torrents WHERE `uid`=' . intval( $this->session->userdata('uid') ) . ' AND 
				`transmission_id` =' . $this->db->escape( $id ) . ';';
		$res = $this->db->query( $sql );
		if ( $res->num_rows > 0 ) {
			echo json_encode( $this->TransmissionRPC->stop( $id ) );
		} else {
			echo json_encode( array( 'result' => 'error', 'error' => 'Not your torrent.' ) );
		}
		exit;
	}
	
	
	public function delete( $id ) {
		if ( $this->session->userdata('uid') == FALSE ) {
			echo json_encode( array( 'result' => 'error', 'error' => 'Not logged in.' ) );
			exit;
		}
		$id = intval( $id );
		$this->RPCInit();
		$sql = 'SELECT `real_name`, `size` FROM dft_torrents WHERE `uid`=' . intval( $this->session->userdata('uid') ) . ' AND 
				`transmission_id` =' . $this->db->escape( $id ) . ';';
		$res = $this->db->query( $sql );
		if ( $res->num_rows > 0 ) {
			$this->TransmissionRPC->stop( $id );
			$torrent_info = $res->row();
			unlink( realpath( './users/' ) . '/' . intval( $this->session->userdata('uid') ) . '/torrents/' . $torrent_info->real_name . '.torrent' );
			
			
			$update = 'UPDATE dft_users SET `total_disk_usage`=total_disk_usage-' . $torrent_info->size . ' WHERE `uid`=' . intval( $this->session->userdata('uid') ) . ';';
			$this->db->query( $update );
			
			$this->db->delete( 'dft_torrents', array( 'uid' => $this->session->userdata('uid'), 'transmission_id' => $id ) );
			echo json_encode( $this->TransmissionRPC->remove( $id, TRUE ) );
		} else {
			echo json_encode( array( 'result' => 'error', 'error' => 'Not your torrent.' ) );
		}
		exit;
	}
	
	public function options( $id, $upload, $download ) {
		if ( $this->session->userdata('uid') == FALSE ) {
			echo json_encode( array( 'result' => 'error', 'error' => 'Not logged in.' ) );
			exit;
		}
		$id = intval( $id );
		$this->RPCInit();
		$sql = 'SELECT `transmission_id` FROM dft_torrents WHERE `uid`=' . intval( $this->session->userdata('uid') ) . ' AND 
				`transmission_id` =' . $this->db->escape( $id ) . ';';
		$upload = intval( $upload );
		$download = intval( $download );
		if ( $upload > $this->session->userdata('max_upload') || $download > $this->session->userdata('max_download') ) {
			echo json_encode( array( 'result' => 'error', 'error' => 'Download or upload speed is set too high.' ) );
			exit;
		}
		$res = $this->db->query( $sql );
		if ( $res->num_rows > 0 ) {
			echo json_encode( $this->TransmissionRPC->set( $id, array(
				'uploadLimit' 		=> $upload,
				'downloadLimit' 	=> $download
			) ) );
		} else {
			echo json_encode( array( 'result' => 'error', 'error' => 'Not your torrent.' ) );
		}
		exit;
	}
	
	public function info( $ids ) {
		if ( $this->session->userdata('uid') == FALSE ) {
			echo json_encode( array( 'result' => 'error', 'error' => 'Not logged in.' ) );
			exit;
		}
		
		$ids = explode( ',', $ids );
		$ids = array_map( 'intval', $ids );
		$ids = array_filter( $ids );
		
		if ( empty( $ids ) ) {
			exit;
		}
		
		$str_ids = '"' . implode( '", "', $ids ) . '"';
		
		$this->RPCInit();
		$sql = 'SELECT `transmission_id` FROM dft_torrents WHERE `uid`=' . intval( $this->session->userdata('uid') ) . ' AND 
				`transmission_id` IN (' . $str_ids . ' );';
		$res = $this->db->query( $sql );
		if ( $res->num_rows > 0 && count( $ids ) == $res->num_rows ) {
			echo json_encode( $this->TransmissionRPC->get( $ids , array(
				'id',
				'rateUpload',
				'rateDownload',
				'peersSendingToUs',
				'peersConnected',
				'percentDone',
				'eta',
				'status',
				'downloadLimit',
				'uploadLimit',
				'uploadedEver',
				'downloadedEver'
			)));
		}
		exit;
	}
	
	public function files( $id ) {
		if ( $this->session->userdata('uid') == FALSE ) {
			echo json_encode( array( 'result' => 'error', 'error' => 'Not logged in.' ) );
			exit;
		}
		$id = intval( $id );
		$this->RPCInit();
		$sql = 'SELECT `transmission_id` FROM dft_torrents WHERE `uid`=' . intval( $this->session->userdata('uid') ) . ' AND 
				`transmission_id` =' . $this->db->escape( $id ) . ';';
		$res = $this->db->query( $sql );
		if ( $res->num_rows > 0 ) {
			echo json_encode( $this->TransmissionRPC->get( $id , array(
				'files',
			)));
		} else {
			echo json_encode( array( 'result' => 'error', 'error' => 'Not your torrent.' ) );
		}
		exit;
	}
	
}