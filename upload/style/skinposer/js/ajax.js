function ApplyForm(id) {
	
	BlockVisible('vform',true)
	
	document.getElementById('vform-skin').value = id
	document.getElementById('vform-validator').src = '../instruments/captcha/captcha.php?refresh=' + rand(1337,31337)
	
	var height = document.compatMode=='CSS1Compat' && document.documentElement.clientHeight;
	var scroll = document.body.scrollTop
	if (!isNaN(document.documentElement.scrollTop)) 
	scroll = document.documentElement.scrollTop + document.body.scrollTop
    document.getElementById('vform').style.top =  scroll + (height / 2) +'px'	
}

function ApplySkin() {

	var code  = GetById('vform-key').value	
	var skin  = GetById('vform-skin').value
	
	if (code.length < 4 ) return false
	
	var event = function(response) {

		if ( response['code'] == 2 ) GetById('vformBox').style.backgroundColor = '#b9e37d'
		else { document.location.reload(true); return false; }
	
		GetById('vformBox').innerHTML = rText
		BlockVisible('vformBox',true)	
	}

	SendByXmlHttp('index.php', 'mode=skinposer&do=get&skin_id=' + encodeURIComponent(skin) + code, event)
	return false
}

function uploadSkin() {

	if (GetById('uplskin').value.length < 1 ) return false	
	
	GetById('skin-loader').style.display = 'inline-block'	
	
	var event = function (response) {
	
		GetById('skin-loader').style.display = 'none'		
        clearFileInputField('uplskin')
		
		if (response != null) {	

            if (response['code'] == 0) { 
			
			document.location.href = base_url + 'index.php?mode=skinposer&ratio=' + response['ratio']			
			return false			
			}
			GetById('mBoxUpl').innerHTML = nl2br(response['message'])
			BlockVisible('mBoxUpl',true)		
		} 
	}
	
	sendFormByIFrame('upload-skin', event)
    return false
}