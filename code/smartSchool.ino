#include <Adafruit_AHTX0.h>
#include "config.h"

#define myDebuggg

Adafruit_AHTX0 aht;

#define LDR_PIN 28

void setup() {
#ifdef myDebug
  Serial.begin(115200);
  while(!Serial);
  delay(5000);  // Give time to switch to the serial monitor

  Serial.println("Adafruit AHT10/AHT20 demo!");
#endif

  if (!aht.begin()) {
#ifdef myDebug
    Serial.println("Could not find AHT? Check wiring");
#endif
    while (1)
      delay(10);
  }
#ifdef myDebug
  Serial.println("AHT10 or AHT20 found");
#endif

  pinMode(LDR_PIN, INPUT);

#ifdef myDebug
  Serial.println(F("\nSetup ... "));
#endif

// SPI configuration
  SPI1.setRX(LORA_MISO);
  SPI1.setTX(LORA_MOSI);
  SPI1.setSCK(LORA_SCK);
  SPI1.begin();
  
#ifdef myDebug
  Serial.println(F("Initialise the radio"));
#endif
  ConfigLoRa_t config;
  config.frequency = 868; // The frequency here does not matter, as it will get changed by LoRaWAN anyway
  int state = radio.begin(config);
  debug(state != RADIOLIB_ERR_NONE, F("Initialise radio failed"), state, true);

  // Setup the OTAA session information
  state = node.beginOTAA(joinEUI, devEUI, nwkKey, appKey);
  debug(state != RADIOLIB_ERR_NONE, F("Initialise node failed"), state, true);

#ifdef myDebug
  Serial.println(F("Join ('login') the LoRaWAN Network"));
#endif
  state = node.activateOTAA();
  debug(state != RADIOLIB_LORAWAN_NEW_SESSION, F("Join failed"), state, true);

#ifdef myDebug
  Serial.println(F("Ready!\n"));
#endif
}

void loop() {


  sensors_event_t humi, temp;
  aht.getEvent(&humi,
               &temp); // populate temp and humidity objects with fresh data
  Serial.print("Temperature: ");
  Serial.print(temp.temperature);
  Serial.println(" degrees C");
  Serial.print("Humidity: ");
  Serial.print(humi.relative_humidity);
  Serial.println("% rH");

  delay(1000);

#ifdef myDebug
  Serial.println(F("Sending uplink"));
#endif

  // This is the place to gather the sensor inputs
  // Instead of reading any real sensor, we just generate some random numbers as example
  int16_t temperature = (int16_t)(temp.temperature * 100.0);
  uint16_t humidity = (uint16_t)(humi.relative_humidity * 100.0);
  uint16_t luminosity = analogRead(LDR_PIN);


#ifdef myDebug
  Serial.print(F("Temperature = "));
  Serial.println(temperature);
  Serial.print(F("Humidity = "));
  Serial.println(humidity);
  Serial.print(F("Luminosity = "));
  Serial.println(analogRead(LDR_PIN));
#endif

  // Build payload byte array
  uint8_t uplinkPayload[6];
  uplinkPayload[0] = highByte(temperature);
  uplinkPayload[1] = lowByte(temperature);
  uplinkPayload[2] = highByte(humidity);
  uplinkPayload[3] = lowByte(humidity);
  uplinkPayload[4] = highByte(luminosity);
  uplinkPayload[5] = lowByte(luminosity);
  
  // Perform an uplink
  int16_t state = node.sendReceive(uplinkPayload, sizeof(uplinkPayload));    
  debug(state < RADIOLIB_ERR_NONE, F("Error in sendReceive"), state, false);

  // Check if a downlink was received 
  // (state 0 = no downlink, state 1/2 = downlink in window Rx1/Rx2)
  if(state > 0) {
#ifdef myDebug
    Serial.println(F("Received a downlink"));
#endif
  } else {
#ifdef myDebug
    Serial.println(F("No downlink received"));
#endif
  }

#ifdef myDebug
  Serial.print(F("Next uplink in "));
  Serial.print(uplinkIntervalSeconds);
  Serial.println(F(" seconds\n"));
#endif
  
  // Wait until next uplink - observing legal & TTN FUP constraints
  delay(uplinkIntervalSeconds * 1000UL);  // delay needs milli-seconds
}
