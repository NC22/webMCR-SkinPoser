function ApplyForm(id) {
	
	BlockVisible('vform',true)
	
	GetById('vform-skin').value = id
	var img = GetById('vform-validator')

	img.src = base_url + 'instruments/captcha/captcha.php?refresh=' + rand(1337,31337)
	img.onload = function(){img.width = 70; img.height = 30}
	
	var margin = Math.round(GetScrollTop() + (getClientH()/2) - (260/2))				
	GetById('vform').style.top =  margin + 'px' 
}

function ApplySkin() {

	var code  = GetById('vform-key').value	
	var skin  = GetById('vform-skin').value
	
	if (code.length < 4 ) return false
	
	var event = function(response) {

		if ( response['code'] == 0 ) { document.location.reload(true); return false; }
		
		GetById('vform-validator').src = base_url + 'instruments/captcha/captcha.php?refresh=' + rand(1337,31337)
		GetById('vformBox').innerHTML = response['message']
		BlockVisible('vformBox',true)	
	}

	SendByXmlHttp('index.php', 'mode=skinposer&do=get&skin_id=' + encodeURIComponent(skin) + '&antibot=' + code, event)
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