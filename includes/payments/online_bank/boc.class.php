<?php
/**
* 开发单位：宁波易科中页信息技术有限公司
* 作者：by @educk
* 时间：2012-3-20
*
*
* 运行环境：php>=4.0.6 and php>=5.0； php需要有openSSL库的支持，安装方法请参考 http://www.php.net/manual/en/openssl.installation.php
* 
* 证书说明：本程序不支持 .pfx 类型的证书，请拆分证书。
*			1、不带key的客户端证书取得： openssl pkcs12 -in filename.pfx -clcerts -nokeys -out cert.pem 
*			2、私有key的证书取得： openssl pkcs12 -in filename.pfx -nocerts -out key.pem
*/
class boc{
	/**
	 * 签名数据
	 */
	var $signData;

	/**
	 * 服务器证书摘要，用来验证客户端接收到的数据是否为伪造
	 *
	 * $dn 为array数组
	 */
	var $dn;

	/**
	 * 服务器证书摘要数据
	 *
	 * $dn 为array数组
	 */
	var $dnData;

	/**
	 * 客户端证书，需要不带密码的pem格式
	 */
	var $cert;

	/**
	 * 私有KEY，需要pem格式
	 */
	var $privateKey;

	/**
	 * 私有KEY密码
	 */
	var $password;

	/**
	 * 消息ID代码
	 */
	var $messageId;

	/**
	 * 存放临时文件目录
	 */
	var $tmpDir;



	/**
	 * PHP5构造函数
	 */
	function __construct($password){
		$this->tmpDir = dirname(__FILE__).'/temp';
		$this->cert = dirname(__FILE__).'/cert/cert.pem';
		$this->privateKey = dirname(__FILE__).'/cert/key.pem';
		$this->dn = array();
		$this->password = $password;
	}

	/**
	 * PHP4构造函数
	 */
	function boc($password){
		$this->__construct($password);
	}

	/**
	 * 检测运行环境，是否满足需要
	 */
	function checkenv(){
		if(!function_exists("openssl_pkcs7_sign")) die("请首先安装openSSL库 http://www.php.net/manual/en/openssl.installation.php");
		if(!file_exists($this->cert)) die("客户端证书文件不存在！".$this->cert);
		if(!file_exists($this->privateKey)) die("客户端口令证书文件不存在！".$this->privateKey);
		if(!file_exists($this->tmpDir)) die("指定的临时目录不存在！".$this->tmpDir);
		$tempDir = $this->tmpDir.'/test';
		if(!@mkdir($tempDir,0777)) die($this->tmpDir.' 文件夹不可写入操作！');
		else @rmdir($tempDir);
	}

	/**
	 * 对数据进行签名
	 *
	 * @param orderNo 商户订单号
	 * @param orderTime 订单时间
	 * @param curCode 订单币种
	 * @param orderAmount 订单金额
	 * @param merchantNo 商户号
	 *
	 * @return 字符串 签名后的数据
	 */
	function signFromParam($orderNo, $orderTime, $curCode, $orderAmount, $merchantNo){
		$data_str = $orderNo.'|'.$orderTime.'|'.$curCode.'|'.$this->formatOrderAmount($orderAmount).'|'.$merchantNo;
		return $this->signFromStr($data_str);
	}

	/**
	 * 对数据进行签名
	 *
	 * @param data_str 明文数据
	 *
	 * @return 字符串 签名后的数据
	 */
	function signFromStr($data_str){
		$sourceFile = tempnam($this->tmpDir,"source");
		$targetFile = tempnam($this->tmpDir,"target");
		file_put_contents($sourceFile,$data_str);
		openssl_pkcs7_sign($sourceFile,$targetFile,"file://".$this->cert,array("file://".$this->privateKey,$this->password),null,PKCS7_DETACHED|0x200|0x100);
		$this->signData = $this->formatSignData(file_get_contents($targetFile));
		@unlink($sourceFile);
		@unlink($targetFile);
		return $this->signData;
	}

	/**
	 * 对签名数据进行验证
	 *
	 * @param signData 签名数据
	 * @param unsignData 明文数据
	 *
	 * @return 验证失败返回FALSE ，验证成功返回TRUE
	 */
	function verifyFormStr($signData,$unsignData){
		$sourceFile = tempnam($this->tmpDir,"source");
		$targetFile = tempnam($this->tmpDir,"target");

		$reVal = false;
		file_put_contents($sourceFile,$this->formatSmimeSignData($signData,$unsignData));
		if(openssl_pkcs7_verify($sourceFile,PKCS7_BINARY|PKCS7_NOVERIFY,$targetFile) === true){
			if($this->dn && is_array($this->dn) && count($this->dn)>0){
				if($this->checkDN($targetFile,$this->dn)===0) $reVal = true;
			}else $reVal = true;
		}
		@unlink($sourceFile);
		@unlink($targetFile);
		return $reVal;
	}

	function checkDN($filename,$dn){
		$this->dnData = openssl_x509_parse("file://".$filename);
		if(!$this->dnData) return $this->messageId=1; //文件解析错误
		if(time()<intval($this->dnData['validFrom_time_t']) || time()>intval($this->dnData['validTo_time_t'])) return $this->messageId=2; //证书时间不正确
		foreach($dn as $k=>$v){
			if(!array_key_exists($k,$this->dnData["subject"]) || $v!=$this->dnData["subject"][$k]) return $this->messageId=$k;
		}
		return $this->messageId=0;
	}

	/**
	 * 获得验证DN定义消息
	 *
	 * @param messageCode 消息代码
	 *
	 * @return 
	 */
	function getMessage($messageCode){
		$m_arr = array(0=>"DN验证正确","DN文件解析错误","DN证书时间过期");
		if(!isset($messageCode) || $messageCode===null || $messageCode==="") return;
		elseif(array_key_exists($messageCode,$m_arr)) return $m_arr[$messageCode];
		elseif(array_key_exists($messageCode,$this->dn)) return "DN[$messageCode]项不匹配";
		else return "未定义消息[$messageCode]";
	}

	/**
	 * 对订单金额后补0操作
	 *
	 * @param orderAmount 订单金额
	 */
	function formatOrderAmount($orderAmount){
		return sprintf("%0.2f",$orderAmount);
	}

	/**
	 * 把S/MIME格式数据格式成签名数据
	 *
	 * @param smimeSignData S/MIME格式数据
	 */
	function formatSignData($smimeSignData){
		$prefix = "||";
		$smimeSignData=str_replace("\n",$prefix,trim($smimeSignData));

		$tmpData = array();
		preg_match_all("/\|{4}(.*)\|{4}/Ui",trim($smimeSignData),$tmpData);
		if(isset($tmpData[1][1])) $smimeSignData=str_replace("||","",$tmpData[1][1]);
		else $smimeSignData="";

		return $smimeSignData;
	}

	/**
	 * 把签名数据格式化成S/MIME格式数据
	 *
	 * @param signData 签名数据
	 * @param unsignData 明文数据
	 */
	function formatSmimeSignData($signData,$unsignData){
		$signData = chunk_split($signData,76,"\n");
		$boundary = "----".md5($signData);

		$signData = <<<EOD
MIME-Version: 1.0
Content-Type: multipart/signed; protocol="application/x-pkcs7-signature"; micalg=sha1; boundary="$boundary"

This is an S/MIME signed message

--$boundary
$unsignData
--$boundary
Content-Type: application/x-pkcs7-signature; name="smime.p7s"
Content-Transfer-Encoding: base64
Content-Disposition: attachment; filename="smime.p7s"

$signData

--$boundary--


EOD;
	
	return $signData;
	}

	/**
	 * 设置用于验证的CA证书
	 *
	 * @param DN 数组DN信息，例array("C"=>"CN","O"=>"BANK OF CHINA","ST"=>"BEIJING","L"=>"BEIJING","CN"=>"BOC CA");
	 */
	function setDN($DN){
		$this->dn = $DN;
	}

}

if (!function_exists('file_put_contents')) {
    function file_put_contents($filename, $data) {
        $f = @fopen($filename, 'w');
        if (!$f) {
            return false;
        } else {
            $bytes = fwrite($f, $data);
            fclose($f);
            return $bytes;
        }
    }
}
?>