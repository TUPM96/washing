#include <WiFi.h>
#include <PubSubClient.h>
#include <WiFiUdp.h>
#include <ArduinoJson.h>
#include <Ticker.h>
#include <NTPClient.h>

Ticker updateTimer; 
WiFiUDP ntpUDP;
NTPClient timeClient(ntpUDP, "pool.ntp.org", 0, 60000);

// Thông tin Wi-Fi
const char* ssid[] = {"M Coffee"};
const char* password[] = {"1234567890"};
const int wifiCount = sizeof(ssid) / sizeof(ssid[0]);

// Địa chỉ MQTT broker
const char* mqtt_server = "103.146.22.13"; // IP của MQTT broker
const char* mqtt_user = "user1"; // MQTT username
const char* mqtt_password = "12345678"; // MQTT password
const int mqtt_port = 1883; // MQTT port
const char* deviceToken = "FMkgxVUpg8q4ija2TwBN"; // Token thiết bị

#define ledSignal 2   // D4

#define DryLevel 21        // D17 Relay 6  
#define Start    22        // D18 Relay 7
#define led2     27        // D22
#define Status   34        // D21

#define pin1  12  // D12 Relay 1
#define pin2  13  // D13 Relay 2
#define pin3  14  // D14 Relay 3
#define pin4  18  // D15 Relay 4
#define pin5  19  // D16 Relay 5

WiFiClient espClient;
PubSubClient client(espClient);

unsigned long last_updated = 0; // Lưu giá trị last_updated
unsigned long lastUpdateMillis = 0; // Lưu thời gian millis() lần cuối cùng updateLastActiveTime được gọi

// Biến trạng thái chương trình
bool isRunning = false;
bool notify = false;
bool notifyStart = true;
bool trigger = false;
bool power_check = false;
bool isFirstTime = true;
unsigned long elapsedTime;
int current_program_code = 0;
unsigned long total_time = 0;
static unsigned long  Service_time = 0;
static unsigned long timestart = 0;
static unsigned long lastTime = 0;
bool notify_slack = false;

// Khai báo các hàm
void setup_wifi();
void callback(char* topic, byte* payload, unsigned int length);
void reconnect();
void setup();
void loop();
void checkPower();
void clickStart();
void tat();
void giatAI();
void cotton();
void giat49();
void giat15();
void chan();
void giattay();
void xavat();
void vat();
void vesinh();
void domau();
void contrung();
void hoinuoc();
void giatsay60();
void saythongminh();
void App();
int DLevel(int min);
void updateLastActiveTime();

// Hàm kết nối Wi-Fi
void setup_wifi() {
  Serial.println("Connecting to WiFi...");
  int i = 0;
  while (WiFi.status() != WL_CONNECTED && i < wifiCount) {
    WiFi.begin(ssid[i], password[i]);
    Serial.printf("Connecting to %s\n", ssid[i]);
    int retryCount = 0;
    while (WiFi.status() != WL_CONNECTED && retryCount < 20) {
      delay(500);
      Serial.print(".");
      retryCount++;
    }
    if (WiFi.status() == WL_CONNECTED) {
      Serial.printf("\nConnected to %s\n", ssid[i]);
      timeClient.begin();
      while (!timeClient.update()) {
        timeClient.forceUpdate();
      }
      Serial.println("NTP time: " + timeClient.getFormattedTime());
      break;
    } else {
      Serial.printf("\nFailed to connect to %s\n", ssid[i]);
      i++;
    }
  }
  if (WiFi.status() != WL_CONNECTED) {
    Serial.println("Failed to connect to any WiFi network.");
  }
}

void callback(char* topic, byte* payload, unsigned int length) {
  StaticJsonDocument<200> doc;

  DeserializationError error = deserializeJson(doc, payload, length);
  Serial.println("Received JSON:");
  serializeJson(doc, Serial);
  if (error) {
    Serial.println("Failed to deserialize JSON");
    return;
  }

  // Lấy dữ liệu từ JSON
  int new_program_code = doc["program_code"];
  unsigned long new_time = doc["time"];
  unsigned long new_time_remaining = doc["time_remaining"];
  notify_slack = doc["notify_slack"];

  trigger = true;
  power_check = true;
  isFirstTime = true;

  // In ra JSON đã giải mã
  Serial.println("Received JSON:");
  serializeJson(doc, Serial);
  Serial.println();

  // Cập nhật và xử lý chương trình
  Serial.println("Message received:");
  Serial.print("Program Code: ");
  Serial.println(new_program_code);
  Serial.print("Time: ");
  Serial.println(new_time);
  Serial.print("Time Remaining: ");
  Serial.println(new_time_remaining);

  // Kiểm tra trạng thái máy
  isRunning = digitalRead(Status);
  if (isRunning) {
    Serial.println("Machine Busy!");
  } else {
    current_program_code = new_program_code;
    total_time = new_time;
    notifyStart = false;
    Serial.printf("Program Started! Program Code: %d | Time: %d seconds\n", current_program_code, total_time);
  }
}

// Hàm kết nối MQTT
void reconnect() {
  while (!client.connected()) {
    Serial.print("Attempting MQTT connection...");
    if (client.connect("ESP32Client", mqtt_user, mqtt_password)) {
      Serial.println("connected");
      client.subscribe((String(deviceToken) + "/run").c_str());
    } else {
      Serial.print("failed, rc=");
      Serial.print(client.state());
      Serial.println(" try again in 5 seconds");
      delay(5000);
    }
  }
}

void setup() {
  Serial.begin(115200);
  pinMode(ledSignal, OUTPUT);
  pinMode(led2, OUTPUT);
  pinMode(Start, OUTPUT);
  pinMode(Status, INPUT_PULLDOWN);
  pinMode(DryLevel, OUTPUT);

  pinMode(pin1, OUTPUT);
  pinMode(pin2, OUTPUT);
  pinMode(pin3, OUTPUT);
  pinMode(pin4, OUTPUT);
  pinMode(pin5, OUTPUT);

  digitalWrite(Status, LOW);
  setup_wifi();
  
  client.setServer(mqtt_server, mqtt_port);
  client.setCallback(callback);

  updateTimer.attach(5, updateLastActiveTime);
}

void loop() {
  // Kiểm tra kết nối MQTT
  if (!client.connected()) {
    digitalWrite(ledSignal, LOW);
    reconnect();
  } else {
    digitalWrite(ledSignal, HIGH);
  }
  
  client.loop();
  // Kiểm tra trạng thái máy
  isRunning = digitalRead(Status);

  // Bắt đầu chương trình khi `current_program_code` khác 0 và máy không bận
  if (!isRunning && current_program_code != 0 && elapsedTime == 0 && trigger == true) {
    trigger = false;
    timestart = millis();  // Ghi nhận thời gian bắt đầu
    switch (current_program_code) {
      case 1: giat49(); clickStart(); break;
      case 2: contrung(); clickStart(); break;
      case 3: giat15(); clickStart(); break;
      case 4: giat49(); DLevel(90); clickStart(); break;
      case 5: giat49(); DLevel(120); clickStart(); break;
      case 6: chan(); DLevel(180); clickStart(); break;
      case 7: saythongminh(); DLevel(60); clickStart(); break;
      case 8: saythongminh(); DLevel(90); clickStart(); break;
      case 10: tat(); break;
      case 11: vat(); clickStart(); break;
      case 12: App(); clickStart(); break;
      case 13: vesinh(); clickStart(); break;
    }
    Serial.printf("Program %d started.\n", current_program_code);
  }

  // Đếm thời gian nếu máy đang chạy
  if (isRunning) {
    if (!notifyStart) {
      // Gửi thông điệp trạng thái lên MQTT
      String payload = "{\"program_code\": " + String(current_program_code) + 
      ", \"time\": " + String(total_time) + 
      ", \"time_remaining\": " + String(total_time) + 
      ", \"washing_time\": 0, \"machine_status\": \"start\"" +
      ", \"notify_slack\": " + String(notify_slack) + "}";
      client.publish((String(deviceToken) + "/status").c_str(), payload.c_str());
      Serial.println("Sent attributes: " + payload);
      notifyStart = true;
    }

    notify = false;

    elapsedTime = (millis() - timestart) / 1000; // Đơn vị: giây
    unsigned long time_remain = total_time - elapsedTime;

    // Gửi thời gian làm việc lên MQTT mỗi giây
    if (millis() - lastTime >= 1000 && time_remain > 0) {
      lastTime = millis();
      // Lấy thời gian hiện tại (nếu dùng millis)
      unsigned long lastUpdated = millis(); 

      // Tạo payload bao gồm first_time nếu lần đầu gửi
      String payload = "{\"program_code\": " + String(current_program_code) + 
                        ", \"washing_time\": " + String(elapsedTime) + 
                        ", \"time_remaining\": " + String(time_remain) + 
                        ", \"machine_status\": \"work\"";

      payload += "}";
      Serial.printf("Program Code: %d | Running Time: %lu seconds | Time Remaining: %lu seconds | Machine Status: %s\n",
              current_program_code, elapsedTime, time_remain, "work");
      client.publish((String(deviceToken) + "/running").c_str(), payload.c_str());
      Serial.println("Sent attributes: " + payload);
    }

        // Nếu hết thời gian thì kết thúc chương trình
    if (time_remain <= 0) {
        current_program_code = 0; // Đặt lại mã chương trình
        total_time = 0;
        elapsedTime = 0;
        isRunning = false; // Dừng trạng thái chạy 
        Serial.println("Program finished. Setting program_code to 0.");
        
        // Gửi trạng thái máy nhàn rỗi lên MQTT
        String payload = "{\"program_code\": 0, \"time\": 0, \"time_remaining\": 0, \"washing_time\": 0, \"machine_status\": \"end\"}";
        client.publish((String(deviceToken) + "/status").c_str(), payload.c_str());
        Serial.println("Sent attributes: " + payload);
        
        // tat(); // Đặt tất cả chân về LOW
        return; // Thoát khỏi hàm
    }
  }

  // Khi chương trình kết thúc
  if (!isRunning && notify == false ) {
    Serial.printf("Program %d finished.\n", current_program_code);

    // Gửi thông điệp trạng thái lên MQTT
    String payload = "{\"program_code\": 0, \"time\": 0, \"time_remaining\": 0, \"washing_time\": 0, \"machine_status\": \"end\"}";
    client.publish((String(deviceToken) + "/status").c_str(), payload.c_str());
    Serial.println("Sent attributes: " + payload);

    // Đặt lại trạng thái
    current_program_code = 0;
    total_time = 0;
    elapsedTime = 0;
    tat(); // Đặt tất cả chân về LOW
    notify = true;
  }
}

void checkPower() {
  isRunning = digitalRead(Status);
  if(!isRunning && !power_check) {
    tat(); // Đặt tất cả chân về LOW
    Serial.println("OFF  machine!");
  }
}

void clickStart() {
    static unsigned long previousMillis = 0;
    static int state = 0;

    digitalWrite(Start, HIGH);

    delay(200);

    digitalWrite(Start, LOW);

    delay(2000);

    checkPower();
    power_check = false;
}

void tat() {
  digitalWrite(pin1, LOW);
  digitalWrite(pin2, LOW);
  digitalWrite(pin3, LOW);
  digitalWrite(pin4, LOW);
  digitalWrite(pin5, LOW);
}

void giatAI() {
  digitalWrite(pin1, LOW);
  digitalWrite(pin2, HIGH);
  digitalWrite(pin3, HIGH);
  digitalWrite(pin4, HIGH);
  digitalWrite(pin5, LOW);
}

void cotton() {
  digitalWrite(pin1, LOW);
  digitalWrite(pin2, HIGH);
  digitalWrite(pin3, LOW);
  digitalWrite(pin4, LOW);
  digitalWrite(pin5, HIGH);
}

void giat49() {
  digitalWrite(pin1, HIGH);
  digitalWrite(pin2, HIGH);
  digitalWrite(pin3, LOW);
  digitalWrite(pin4, LOW);
  digitalWrite(pin5, HIGH);
}

void giat15() {
  digitalWrite(pin1, HIGH);
  digitalWrite(pin2, HIGH);
  digitalWrite(pin3, LOW);
  digitalWrite(pin4, LOW);
  digitalWrite(pin5, LOW);
}

void chan() {
  digitalWrite(pin1, HIGH);
  digitalWrite(pin2, HIGH);
  digitalWrite(pin3, LOW);
  digitalWrite(pin4, HIGH);
  digitalWrite(pin5, LOW);
}

void giattay() {
  digitalWrite(pin1, HIGH);
  digitalWrite(pin2, HIGH);
  digitalWrite(pin3, LOW);
  digitalWrite(pin4, HIGH);
  digitalWrite(pin5, HIGH);
}

void xavat() {
  digitalWrite(pin1, LOW);
  digitalWrite(pin2, HIGH);
  digitalWrite(pin3, LOW);
  digitalWrite(pin4, HIGH);
  digitalWrite(pin5, HIGH);
}

void vat() {
  digitalWrite(pin1, LOW);
  digitalWrite(pin2, HIGH);
  digitalWrite(pin3, LOW);
  digitalWrite(pin4, HIGH);
  digitalWrite(pin5, LOW);
}

void vesinh() {
  digitalWrite(pin1, LOW);
  digitalWrite(pin2, LOW);
  digitalWrite(pin3, LOW);
  digitalWrite(pin4, HIGH);
  digitalWrite(pin5, LOW);
}

void domau() {
  digitalWrite(pin1, LOW);
  digitalWrite(pin2, LOW);
  digitalWrite(pin3, LOW);
  digitalWrite(pin4, HIGH);
  digitalWrite(pin5, HIGH);
}

void contrung() {
  digitalWrite(pin1, HIGH);
  digitalWrite(pin2, LOW);
  digitalWrite(pin3, LOW);
  digitalWrite(pin4, HIGH);
  digitalWrite(pin5, HIGH);
}

void hoinuoc() {
  digitalWrite(pin1, HIGH);
  digitalWrite(pin2, LOW);
  digitalWrite(pin3, LOW);
  digitalWrite(pin4, HIGH);
  digitalWrite(pin5, LOW);
}

void giatsay60() {
  digitalWrite(pin1, HIGH);
  digitalWrite(pin2, LOW);
  digitalWrite(pin3, LOW);
  digitalWrite(pin4, LOW);
  digitalWrite(pin5, LOW);
}

void saythongminh() {
  digitalWrite(pin1, HIGH);
  digitalWrite(pin2, LOW);
  digitalWrite(pin3, LOW);
  digitalWrite(pin4, LOW);
  digitalWrite(pin5, HIGH);
}

void App() {
  digitalWrite(pin1, HIGH);
  digitalWrite(pin2, HIGH);
  digitalWrite(pin3, HIGH);
  digitalWrite(pin4, LOW);
  digitalWrite(pin5, HIGH);
}

int DLevel(int min) {
  int pulses = 0;

  // Xác định số xung dựa trên giá trị của min
  if (min == 90) {
    pulses = 6;
  } else if (min == 120) {
    pulses = 7;
  } else if (min == 180) {
    pulses = 8;
  } else if (min == 30) {
    pulses = 3;
  } else if (min == 60) {
    pulses = 4;
  }

  // Tạo ra các xung trên chân DryLevel
  unsigned long previousMillis = 0;
  unsigned long interval = 300;
  for (int i = 0; i < pulses; ) {
      unsigned long currentMillis = millis();
      if (currentMillis - previousMillis >= interval) {
          previousMillis = currentMillis;
          digitalWrite(DryLevel, !digitalRead(DryLevel)); // Chuyển đổi trạng thái chân
          if (digitalRead(DryLevel) == LOW) i++; // Tăng biến đếm khi chân trở về LOW
      }
  }

  return min;
}

void updateLastActiveTime() {
  unsigned long lastUpdated = timeClient.getEpochTime(); // Thời gian hiện tại
  String payload = "{\"last_active_time\": " + String(lastUpdated) + "}";
  client.publish((String(deviceToken) + "/realtime").c_str(), payload.c_str());
  Serial.println("Sent last_active_time: " + payload);
}