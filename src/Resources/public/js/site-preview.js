/**
 * Dify站点预览功能
 * 在模态框中使用iframe预览站点，并显示应用信息
 */
function openSitePreview(url, appName = '', appType = '')
{
    if (!url || url.trim() === "" || url === "#") {
        alert("站点URL无效");
        return false;
    }

    // 创建模态框
    const modal = document.createElement("div");
    modal.className = "dify-site-preview-modal";
    modal.style.cssText = `
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0,0,0,0.8);
        z-index: 9999;
        display: flex;
        flex-direction: column;
        justify-content: center;
        align-items: center;
        font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
    `;

    // 创建内容容器
    const container = document.createElement("div");
    container.style.cssText = `
        width: 90%;
        height: 90%;
        background: white;
        border-radius: 8px;
        display: flex;
        flex-direction: column;
        overflow: hidden;
        box-shadow: 0 10px 30px rgba(0,0,0,0.3);
    `;

    // 创建头部信息栏
    const header = document.createElement("div");
    header.style.cssText = `
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        padding: 12px 20px;
        display: flex;
        justify-content: space-between;
        align-items: center;
        font-size: 14px;
        min-height: 48px;
    `;

    // 应用信息
    const appInfo = document.createElement("div");
    appInfo.style.cssText = `
        display: flex;
        flex-direction: column;
        gap: 2px;
    `;

    const appTitle = document.createElement("div");
    appTitle.style.cssText = `
        font-weight: 600;
        font-size: 16px;
    `;
    appTitle.textContent = appName || '站点预览';

    const appMeta = document.createElement("div");
    appMeta.style.cssText = `
        font-size: 12px;
        opacity: 0.9;
    `;

    let metaText = '';
    if (appType) {
        const typeLabels = {
            'chat': '聊天应用',
            'agent-chat': '智能助手',
            'advanced-chat': '高级聊天',
            'chatflow': '聊天流应用',
            'workflow': '工作流应用'
        };
        metaText = typeLabels[appType] || appType;
    }

    const domain = new URL(url).hostname;
    metaText += metaText ? ` • ${domain}` : domain;
    appMeta.textContent = metaText;

    appInfo.appendChild(appTitle);
    appInfo.appendChild(appMeta);

    // 关闭按钮
    const closeBtn = document.createElement("button");
    closeBtn.innerHTML = "×";
    closeBtn.style.cssText = `
        background: rgba(255,255,255,0.2);
        color: white;
        border: none;
        width: 32px;
        height: 32px;
        border-radius: 50%;
        font-size: 18px;
        cursor: pointer;
        transition: background 0.2s;
        display: flex;
        align-items: center;
        justify-content: center;
    `;

    closeBtn.onmouseover = () => closeBtn.style.background = "rgba(255,255,255,0.3)";
    closeBtn.onmouseout = () => closeBtn.style.background = "rgba(255,255,255,0.2)";

    header.appendChild(appInfo);
    header.appendChild(closeBtn);

    // 创建iframe
    const iframe = document.createElement("iframe");
    iframe.src = url;
    iframe.style.cssText = `
        flex: 1;
        border: none;
        background: white;
    `;

    // 加载状态
    const loadingDiv = document.createElement("div");
    loadingDiv.style.cssText = `
        position: absolute;
        top: 50%;
        left: 50%;
        transform: translate(-50%, -50%);
        color: #666;
        font-size: 14px;
        display: flex;
        align-items: center;
        gap: 8px;
    `;
    loadingDiv.innerHTML = `
        <div style="
            width: 16px;
            height: 16px;
            border: 2px solid #f3f3f3;
            border-top: 2px solid #667eea;
            border-radius: 50%;
            animation: spin 1s linear infinite;
        "></div>
        正在加载站点...
    `;

    // 添加旋转动画
    const style = document.createElement('style');
    style.textContent = `
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
    }
    `;
    document.head.appendChild(style);

    container.appendChild(header);
    container.appendChild(iframe);
    container.appendChild(loadingDiv);

    // iframe加载完成后隐藏loading
    iframe.onload = () => {
        loadingDiv.style.display = 'none';
        style.remove();
    };

    // 关闭功能
    const closeModal = () => {
        if (document.body.contains(modal)) {
            document.body.removeChild(modal);
        }
    };

    closeBtn.onclick = closeModal;
    // 移除点击背景关闭功能
    // modal.onclick = (e) => {
    //     if (e.target === modal) closeModal();
    // };

    // ESC键关闭
    const handleKeyDown = (e) => {
        if (e.key === 'Escape') {
            closeModal();
            document.removeEventListener('keydown', handleKeyDown);
        }
    };
    document.addEventListener('keydown', handleKeyDown);

    modal.appendChild(container);
    document.body.appendChild(modal);
}