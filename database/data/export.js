import fs from 'fs';
import phil from 'phil-reg-prov-mun-brgy';

// Export data to JSON files
fs.writeFileSync('regions.json', JSON.stringify(phil.regions));
fs.writeFileSync('provinces.json', JSON.stringify(phil.provinces));
fs.writeFileSync('municipalities.json', JSON.stringify(phil.city_mun));
fs.writeFileSync('barangays.json', JSON.stringify(phil.barangays));

console.log('Export complete!');