#!/bin/sh

if [ "$DRUPCLEAN_NUKE_CONFIRM" = "I UNDERSTAND" ]; then 
  echo "Confirmed: You provided the required confirmation string";
  exit 0;
else 
  echo "Abort: You did not provide the required confirmation string";
  exit 255;
fi
