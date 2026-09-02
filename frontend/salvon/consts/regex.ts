export const emailRegex = /^[A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,4}$/i;
export const numberRegex = /^-?\d+([.,]\d+)?$/;
export const positiveIntRegex = /^\d+$/;
export const polishPhoneNumber = /^[1-9]\d{8}$/;
export const startingEndingSlashes = /^\/|\/$/g;
export const passwordRegex =
  /^(?=.*[A-Z])(?=.*\d)(?=.*[^A-Za-z0-9])(?=.*[!@#$%^&*()\-_=+{},<.>/?;:'"\\|\]\[`~]).{8,}$/;
