var loginForm = document.querySelector('form');
var captchaDiv = document.getElementById('captcha');
var loginButton;

// 核心把视图变量 extra 序列化进 #blessing-extra，前端读作 blessing.extra.*
// 与原实现一致，等 DOMContentLoaded 再读，不依赖本脚本与核心 bundle 的执行顺序
var sitekey = '';
var invisible = false;
var recaptchaWidgetId = null;

function lockButton() {
    if (!loginButton) return;
    loginButton.disabled = true;
    loginButton.innerHTML = '<i class="fas fa-spinner fa-spin mr-1"></i>' + blessing.t('auth.registering');
}

function unlockButton() {
    if (!loginButton) return;
    loginButton.disabled = false;
    loginButton.innerHTML = blessing.t('auth.register');
}

function resetCaptcha() {
    if (sitekey) {
        if (recaptchaWidgetId !== null) grecaptcha.reset(recaptchaWidgetId);
    } else {
        document.getElementById('captcha-img').src = blessing.base_url + '/auth/captcha?v=' + new Date().getTime();
    }
}

// token 仅在隐形 reCAPTCHA 的回调路径中传入。
async function submit(token) {
    var formData = new FormData(loginForm);
    var captcha = token || (sitekey ? getRecaptchaResponse() : formData.get('captcha'));
    const response = await blessing.fetch.post('/auth/register/eduroam', {
        user: formData.get('user'),
        password: formData.get('password'),
        qq: formData.get('qq'),
        player_name: formData.get('player_name') || undefined,
        nickname: formData.get('nickname') || undefined,
        captcha: captcha
    })
    if (response.code === 0) window.location.href = response.data.redirectTo;
    else {
        var warningDiv = document.getElementById('warning');
        var warningText = document.getElementById('warning-text')
        warningText.innerText = response.message;
        warningDiv.style.display = null;
        unlockButton();
        resetCaptcha();
    }
}

function getRecaptchaResponse() {
    if (recaptchaWidgetId === null) return '';
    return grecaptcha.getResponse(recaptchaWidgetId) || '';
}

var recaptchaCallback = function(token) {
    submit(token)
}

// token 过期或 reCAPTCHA 自身出错时解锁按钮，避免表单卡在“注册中”。
var recaptchaAbortCallback = function() {
    unlockButton();
}

var onloadCallback = function() {
    recaptchaWidgetId = grecaptcha.render('recaptcha', {
        sitekey: sitekey,
        size: invisible ? 'invisible' : 'normal',
        callback: invisible ? recaptchaCallback : undefined,
        'expired-callback': invisible ? recaptchaAbortCallback : undefined,
        'error-callback': invisible ? recaptchaAbortCallback : undefined
    });
    // 隐形模式在 widget 就绪前拿不到 token，此时才放开提交
    if (invisible) unlockButton();
};

function loadCaptcha() {
    captchaDiv.innerHTML = `
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
}

function loadRecaptcha() {
    // 图形验证码那套横排布局对 reCAPTCHA 不适用
    captchaDiv.classList.remove('d-flex');
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
    loginButton = document.getElementById('loginButton');
    var extra = (window.blessing && blessing.extra) || {};
    sitekey = extra.recaptcha || '';
    invisible = !!extra.invisible;
    if (sitekey) {
        loadRecaptcha();
        // 隐形模式先锁住按钮，等 onloadCallback 渲染完 widget 再解锁
        if (invisible) lockButton();
    } else loadCaptcha();
    loginForm.addEventListener('submit', function(event) {
        event.preventDefault();
        document.getElementById('warning').style.display = 'none';
        lockButton();
        // 隐形模式由 recaptchaCallback 拿到 token 后再提交
        if (sitekey && invisible) {
            if (recaptchaWidgetId === null) {
                unlockButton();
                return;
            }
            grecaptcha.execute(recaptchaWidgetId);
        } else submit();
    });
});
