var loginForm = document.querySelector('form'); 
var captchaDiv = document.getElementById('captcha')
async function submit(formData, captcha) {
    const response = await blessing.fetch.post('/auth/register/eduroam', {
        user: formData.get('user'),
        password: formData.get('password'),
        qq: formData.get('qq'),
        player_name: formData.get('player_name') || undefined,
        nickname: formData.get('nickname') || undefined,
        captcha: formData.get('captcha')
    })
    if(response.code === 0) window.location.href = response.data.redirectTo;
    else {
        var warningDiv = document.getElementById('warning');
        var warningText = document.getElementById('warning-text')
        warningText.innerText = response.message;
        warningDiv.style.display = null;
        loginButton.disabled = false;
        loginButton.innerHTML = blessing.t('auth.register');
        if(blessing.recaptcha) grecaptcha.reset();
        else document.getElementById('captcha-img').src=blessing.base_url+'/auth/captcha?v='+new Date().getTime();
    }
}
var recaptchaCallback = function(token) {
    submit(new FormData(loginForm), token)
}
var onloadCallback = function() {
    grecaptcha.render('recaptcha', {
        sitekey: blessing.recaptcha,
        size:blessing.invisible ? 'invisible' : '',
        callback:blessing.invisible ? 'recaptchaCallback' : undefined
    });
};
function loadCaptcha() {
    captcha.innerHTML = `
    <div class="form-group mb-3 mr-2">
        <input type="text" class="form-control" placeholder="${blessing.t('auth.captcha')}" name="captcha" required value="" />
    </div>
    <img src="${blessing.base_url+'/auth/captcha?v='+new Date().getTime()}"
    alt="${blessing.t('auth.captcha')}"
    style="cursor: pointer;"
    height="34"
    title="${blessing.t('auth.change-captcha')}"
    onClick="this.src=blessing.base_url+'/auth/captcha?v='+new Date().getTime()"
    id="captcha-img"
    />`;
    captchaDiv.style.display = null;
}
function loadRecaptcha() {
    const outerDiv = document.createElement('div');
    outerDiv.className = 'mb-2';
    const recaptchaDiv = document.createElement('div');
    recaptchaDiv.id = 'recaptcha'
    const apiScript = document.createElement('script');
    apiScript.src = 'https://recaptcha.net/recaptcha/api.js?onload=onloadCallback&render=explicit';
    apiScript.async = true;
    apiScript.defer = true;
    outerDiv.appendChild(recaptchaDiv);
    captchaDiv.appendChild(outerDiv);
    captchaDiv.appendChild(apiScript);
}
document.addEventListener("DOMContentLoaded", function() {
    if(blessing.recaptcha) loadRecaptcha();
    else loadCaptcha();
    loginForm.addEventListener('submit', function(event) {
        event.preventDefault();
        var warningDiv = document.getElementById('warning');
        warningDiv.style.display = 'none';
        var loginButton = document.getElementById('loginButton');
        loginButton.disabled = true;
        loginButton.innerHTML = '<i class="fas fa-spinner fa-spin mr-1"></i>' + blessing.t('auth.registering');
        if(blessing.recaptcha && blessing.invisible) grecaptcha.execute();
        else submit(new FormData(loginForm));
    });
});