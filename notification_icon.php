<?php
// notification_icon.php - Include this in your navigation
if(!isset($conn)) {
    include_once "../DBConn.php";
}

$unread_messages = getUnreadMessageCount($conn, $_SESSION['user_id']);
$unread_notifications = getUnreadNotificationCount($conn, $_SESSION['user_id']);
$total_unread = $unread_messages + $unread_notifications;
?>

<style>
.notification-icon {
    position: relative;
    display: inline-block;
    cursor: pointer;
}

.notification-badge {
    position: absolute;
    top: -8px;
    right: -8px;
    background: #c44536;
    color: white;
    border-radius: 50%;
    min-width: 18px;
    height: 18px;
    font-size: 10px;
    font-weight: bold;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 0 4px;
    animation: pulse 1.5s infinite;
}

@keyframes pulse {
    0% { transform: scale(1); }
    50% { transform: scale(1.1); }
    100% { transform: scale(1); }
}

.notification-dropdown {
    display: none;
    position: absolute;
    top: 35px;
    right: 0;
    width: 350px;
    max-height: 400px;
    background: white;
    border: 1px solid var(--border);
    border-radius: 8px;
    box-shadow: 0 5px 20px rgba(0,0,0,0.15);
    z-index: 1000;
    overflow: hidden;
}

.notification-dropdown.show {
    display: block;
}

.notification-header {
    padding: 12px 15px;
    border-bottom: 1px solid var(--border);
    font-weight: 600;
    font-size: 14px;
    background: #fafafa;
}

.notification-list {
    max-height: 350px;
    overflow-y: auto;
}

.notification-item {
    padding: 12px 15px;
    border-bottom: 1px solid var(--border);
    cursor: pointer;
    transition: background 0.2s;
    text-decoration: none;
    display: block;
    color: inherit;
}

.notification-item:hover {
    background: #f5f5f5;
}

.notification-item.unread {
    background: #fffbf0;
}

.notification-title {
    font-weight: 600;
    font-size: 13px;
    margin-bottom: 4px;
}

.notification-message {
    font-size: 11px;
    color: #666;
    margin-bottom: 4px;
}

.notification-time {
    font-size: 10px;
    color: #999;
}

.notification-footer {
    padding: 10px 15px;
    border-top: 1px solid var(--border);
    text-align: center;
    background: #fafafa;
}

.notification-footer a {
    text-decoration: none;
    font-size: 12px;
    color: var(--gold);
}

.empty-notifications {
    padding: 30px;
    text-align: center;
    color: #999;
    font-size: 13px;
}
</style>

<div class="notification-icon" onclick="toggleNotifications(event)">
    <span>🔔</span>
    <?php if($total_unread > 0): ?>
        <span class="notification-badge"><?php echo $total_unread > 9 ? '9+' : $total_unread; ?></span>
    <?php endif; ?>
    
    <div class="notification-dropdown" id="notificationDropdown">
        <div class="notification-header">
            Notifications (<?php echo $total_unread; ?> unread)
        </div>
        <div class="notification-list" id="notificationList">
            <!-- Will be loaded via AJAX -->
            <div style="text-align: center; padding: 20px;">Loading...</div>
        </div>
        <div class="notification-footer">
            <a href="../messages.php">View all messages →</a>
        </div>
    </div>
</div>

<script>
function toggleNotifications(event) {
    event.stopPropagation();
    const dropdown = document.getElementById('notificationDropdown');
    dropdown.classList.toggle('show');
    
    if (dropdown.classList.contains('show')) {
        loadNotifications();
    }
}

function loadNotifications() {
    fetch('get_notifications.php')
        .then(response => response.json())
        .then(data => {
            const container = document.getElementById('notificationList');
            if (data.notifications.length === 0) {
                container.innerHTML = '<div class="empty-notifications">No new notifications</div>';
                return;
            }
            
            let html = '';
            data.notifications.forEach(notif => {
                html += `
                    <a href="${notif.link}" class="notification-item ${notif.is_read == 0 ? 'unread' : ''}">
                        <div class="notification-title">${notif.title}</div>
                        <div class="notification-message">${notif.message}</div>
                        <div class="notification-time">${notif.time_ago}</div>
                    </a>
                `;
            });
            container.innerHTML = html;
        })
        .catch(error => {
            console.error('Error loading notifications:', error);
        });
}

// Close dropdown when clicking outside
document.addEventListener('click', function() {
    const dropdown = document.getElementById('notificationDropdown');
    dropdown.classList.remove('show');
});

// Auto-refresh notifications every 30 seconds
setInterval(function() {
    if (document.getElementById('notificationDropdown').classList.contains('show')) {
        loadNotifications();
    }
    // Update badge count
    fetch('get_notification_count.php')
        .then(response => response.json())
        .then(data => {
            const badge = document.querySelector('.notification-badge');
            if (data.total > 0) {
                if (badge) {
                    badge.textContent = data.total > 9 ? '9+' : data.total;
                } else {
                    document.querySelector('.notification-icon').innerHTML += 
                        `<span class="notification-badge">${data.total > 9 ? '9+' : data.total}</span>`;
                }
            } else if (badge) {
                badge.remove();
            }
        });
}, 30000);
</script>