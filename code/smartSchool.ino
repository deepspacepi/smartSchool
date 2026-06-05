#include <Wire.h>

void setup() {
  Serial.begin(115200);
while(!Serial); delay(5000);
  Wire.begin();

  Serial.println("Scanning...");
  for (byte addr = 1; addr < 127; addr++) {
    Wire.beginTransmission(addr);
    if (Wire.endTransmission() == 0) {
      Serial.print("Found device at 0x");
      Serial.println(addr, HEX);
    }
  }
  Serial.print("End Scanning");
}

void loop() {}
