<!DOCTYPE html>
<html>
<head>
    <title>登入測試</title>
    <script src="https://cdn.jsdelivr.net/npm/axios/dist/axios.min.js"></script>
    <style>
        .container { max-width: 600px; margin: 50px auto; padding: 20px; }
        .form-group { margin-bottom: 15px; }
        .form-group label { display: block; margin-bottom: 5px; }
        .form-group input { width: 100%; padding: 8px; }
        button { padding: 10px 20px; background: #007bff; color: white; border: none; cursor: pointer; }
        #response { margin-top: 20px; padding: 10px; border: 1px solid #ddd; }
    </style>
</head>
<body>
    <div class="container">
        <h2>登入測試</h2>
        
        <div class="form-group">
            <label>用戶名</label>
            <input type="text" id="username" value="test">
        </div>
        
        <div class="form-group">
            <label>密碼</label>
            <input type="password" id="password" value="password123">
        </div>

        <button onclick="login()">登入</button>
        <button onclick="getCurrentUser()">獲取當前用戶</button>
        <button onclick="logout()">登出</button>

        <div id="response"></div>
    </div>

    <script>
    const baseUrl = '/api';
    const responseDiv = document.getElementById('response');

    function showResponse(data) {
        responseDiv.innerHTML = '<pre>' + JSON.stringify(data, null, 2) + '</pre>';
    }

    async function login() {
        try {
            const username = document.getElementById('username').value;
            const password = document.getElementById('password').value;
            
            const response = await axios.post(`${baseUrl}/login`, {
                username: username,
                password: password
            });
            
            showResponse(response.data);
        } catch (error) {
            showResponse(error.response?.data || error.message);
        }
    }

    async function getCurrentUser() {
        try {
            const response = await axios.get(`${baseUrl}/user`);
            showResponse(response.data);
        } catch (error) {
            showResponse(error.response?.data || error.message);
        }
    }

    async function logout() {
        try {
            const response = await axios.get(`${baseUrl}/logout`);
            showResponse(response.data);
        } catch (error) {
            showResponse(error.response?.data || error.message);
        }
    }
    </script>
</body>
</html> 